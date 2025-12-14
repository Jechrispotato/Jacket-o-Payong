<?php
// src/WeatherService.php - Open-Meteo Weather Service

class WeatherService {
    private $geocodingUrl;
    private $forecastUrl;

    public function __construct($geocodingUrl = null, $forecastUrl = null) {
        $this->geocodingUrl = $geocodingUrl ?: 'https://geocoding-api.open-meteo.com/v1/search';
        $this->forecastUrl = $forecastUrl ?: 'https://api.open-meteo.com/v1/forecast';
    }

    /**
     * Get coordinates from city name using Open-Meteo Geocoding API
     * @param string $location City name or "city,country"
     * @return array|false Coordinates array with 'latitude' and 'longitude' or false on error
     */
    private function getCoordinates($location) {
        $url = $this->geocodingUrl . '?name=' . urlencode($location) . '&count=1&language=en&format=json';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['error' => 'Network error: ' . $curlError];
        }

        if ($httpCode !== 200) {
            return ['error' => "Geocoding API error (HTTP $httpCode)"];
        }

        $data = json_decode($response, true);
        
        if (!$data || !isset($data['results']) || empty($data['results'])) {
            return ['error' => 'City not found. Please check the city name and try again.'];
        }

        $result = $data['results'][0];
        return [
            'latitude' => $result['latitude'],
            'longitude' => $result['longitude'],
            'name' => $result['name'],
            'country' => $result['country'] ?? ''
        ];
    }

    /**
     * Fetch weather data from Open-Meteo Forecast API
     * @param string $location City name or "city,country"
     * @return array Weather data array or error array with 'error' key
     */
    public function getWeatherData($location) {
        // First, get coordinates from city name
        $coords = $this->getCoordinates($location);
        
        if (isset($coords['error'])) {
            return $coords;
        }

        $latitude = $coords['latitude'];
        $longitude = $coords['longitude'];
        
        // Build forecast API URL with daily forecast data (7 days)
        $url = $this->forecastUrl . '?' . http_build_query([
            'latitude' => $latitude,
            'longitude' => $longitude,
            'current' => 'temperature_2m,relative_humidity_2m,wind_speed_10m,precipitation,weather_code',
            'daily' => 'temperature_2m_max,temperature_2m_min,relative_humidity_2m_max,wind_speed_10m_max,precipitation_sum,precipitation_probability_max,weather_code',
            'forecast_days' => 7, // Get 7 days forecast
            'timezone' => 'auto'
        ]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['error' => 'Network error: ' . $curlError];
        }

        if ($httpCode !== 200) {
            return ['error' => "Weather API error (HTTP $httpCode)"];
        }

        $data = json_decode($response, true);
        
        if (!$data) {
            return ['error' => 'Invalid response from weather API.'];
        }

        // Add location info to the response
        $data['location'] = [
            'name' => $coords['name'],
            'country' => $coords['country'],
            'latitude' => $latitude,
            'longitude' => $longitude
        ];

        return $data;
    }

    /**
     * Convert Open-Meteo data to model input format
     * Model expects: [temp, humidity, wind, precip, condition]
     * @param array $weatherData Open-Meteo API response
     * @return array Model input format
     */
    public function formatForModel($weatherData) {
        $current = $weatherData['current'];
        
        // Temperature in Celsius
        $temp = round($current['temperature_2m'], 1);
        
        // Humidity percentage
        $humidity = (float) $current['relative_humidity_2m'];
        
        // Wind speed (convert from km/h to km/h - already in km/h)
        $wind = round($current['wind_speed_10m'], 1);
        
        // Precipitation probability - use hourly data if available, otherwise use current
        $precip = 0;
        if (isset($weatherData['hourly']['precipitation_probability']) && !empty($weatherData['hourly']['precipitation_probability'])) {
            // Get the next hour's precipitation probability
            $precip = (float) $weatherData['hourly']['precipitation_probability'][0];
        } elseif (isset($current['precipitation']) && $current['precipitation'] > 0) {
            // If there's current precipitation, estimate probability
            $precip = min(100, round($current['precipitation'] * 50)); // Rough estimate
        }
        
        // Condition mapping from WMO weather code: 0=Sunny, 1=Cloudy, 2=Rain, 3=Snow
        $condition = 0; // Default to sunny
        if (isset($current['weather_code'])) {
            $code = (int) $current['weather_code'];
            
            // WMO Weather Interpretation Codes (WW)
            // Clear or mostly clear: 0-3
            // Cloudy: 4-49, 61-67 (fog, drizzle)
            // Rain: 51-67 (drizzle, rain), 80-99 (rain, thunderstorms)
            // Snow: 71-77 (snow), 85-86 (snow showers)
            
            if ($code >= 71 && $code <= 77) {
                $condition = 3; // Snow
            } elseif (($code >= 51 && $code <= 67) || ($code >= 80 && $code <= 99)) {
                $condition = 2; // Rain
            } elseif ($code >= 4 && $code <= 49) {
                $condition = 1; // Cloudy/Fog
            } else {
                $condition = 0; // Clear/Sunny
            }
        }

        return [
            'temp' => $temp,
            'humidity' => $humidity,
            'wind' => $wind,
            'precip' => $precip,
            'condition' => $condition,
            'raw_data' => $weatherData // Keep raw data for display
        ];
    }

    /**
     * Format daily forecast data for predictions
     * Returns array of formatted data for each day (7 days)
     * @param array $weatherData Open-Meteo API response with daily data
     * @return array Array of formatted daily data
     */
    public function formatDailyForecastForModel($weatherData) {
        if (!isset($weatherData['daily']) || empty($weatherData['daily']['time'])) {
            return [];
        }

        $dailyData = [];
        $daily = $weatherData['daily'];
        
        // Get the number of days available (up to 7 days)
        $count = min(7, count($daily['time']));
        
        for ($i = 0; $i < $count; $i++) {
            // Use average of max and min temperature for prediction
            $tempMax = isset($daily['temperature_2m_max'][$i]) ? (float) $daily['temperature_2m_max'][$i] : 0;
            $tempMin = isset($daily['temperature_2m_min'][$i]) ? (float) $daily['temperature_2m_min'][$i] : 0;
            $temp = round(($tempMax + $tempMin) / 2, 1);
            
            // Use max humidity for the day
            $humidity = isset($daily['relative_humidity_2m_max'][$i]) ? (float) $daily['relative_humidity_2m_max'][$i] : 0;
            
            // Use max wind speed for the day
            $wind = isset($daily['wind_speed_10m_max'][$i]) ? round($daily['wind_speed_10m_max'][$i], 1) : 0;
            
            // Precipitation probability (use max probability for the day)
            $precip = 0;
            if (isset($daily['precipitation_probability_max'][$i])) {
                $precip = (float) $daily['precipitation_probability_max'][$i];
            } elseif (isset($daily['precipitation_sum'][$i]) && $daily['precipitation_sum'][$i] > 0) {
                // If there's precipitation, estimate probability
                $precip = min(100, round($daily['precipitation_sum'][$i] * 20));
            }
            
            // Condition from weather code
            $condition = 0;
            if (isset($daily['weather_code'][$i])) {
                $code = (int) $daily['weather_code'][$i];
                if ($code >= 71 && $code <= 77) {
                    $condition = 3; // Snow
                } elseif (($code >= 51 && $code <= 67) || ($code >= 80 && $code <= 99)) {
                    $condition = 2; // Rain
                } elseif ($code >= 4 && $code <= 49) {
                    $condition = 1; // Cloudy/Fog
                } else {
                    $condition = 0; // Clear/Sunny
                }
            }
            
            $dailyData[] = [
                'date' => $daily['time'][$i],
                'temp_max' => $tempMax,
                'temp_min' => $tempMin,
                'temp' => $temp,
                'humidity' => $humidity,
                'wind' => $wind,
                'precip' => $precip,
                'precipitation_sum' => isset($daily['precipitation_sum'][$i]) ? (float) $daily['precipitation_sum'][$i] : 0,
                'condition' => $condition,
                'sample' => [$temp, $humidity, $wind, $precip, $condition] // Ready for model prediction
            ];
        }
        
        return $dailyData;
    }
}
