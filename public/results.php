<?php
// public/results.php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';
require __DIR__ . '/../src/WeatherService.php';

use Phpml\ModelManager;

function sanitize($v)
{
    return htmlspecialchars(trim($v));
}

$modelManager = new ModelManager();
$jacketModelFile = __DIR__ . '/../models/jacket.model';
$umbrellaModelFile = __DIR__ . '/../models/umbrella.model';

$modelsExist = file_exists($jacketModelFile) && file_exists($umbrellaModelFile);
$jacket = $umbrella = null;
if ($modelsExist) {
    $jacket = $modelManager->restoreFromFile($jacketModelFile);
    $umbrella = $modelManager->restoreFromFile($umbrellaModelFile);
}

$result = null;
$error = null;
$weatherData = null;
$location = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $location = sanitize($_POST['location'] ?? '');

        if (empty($location)) {
            header('Location: /public/error.php?message=' . urlencode('Please enter a city name.'));
            exit;
        } else {
            // Initialize weather service
            $weatherService = new WeatherService(
                defined('GEOCODING_API_URL') ? GEOCODING_API_URL : null,
                defined('FORECAST_API_URL') ? FORECAST_API_URL : null
            );

            // Fetch weather data from API
            $apiData = $weatherService->getWeatherData($location);

            if (isset($apiData['error'])) {
                header('Location: /public/error.php?message=' . urlencode($apiData['error']));
                exit;
            } else {
                // Update location with actual name from API
                if (isset($apiData['location']['name'])) {
                    $location = $apiData['location']['name'];
                    if (!empty($apiData['location']['country'])) {
                        $location .= ', ' . $apiData['location']['country'];
                    }
                }

                // Format weather data for model (current conditions)
                $weatherData = $weatherService->formatForModel($apiData);

                if (!$modelsExist) {
                    header('Location: /public/error.php?message=' . urlencode('Models not found. Please run training (php src/train.php).'));
                    exit;
                } else {
                    // Get daily forecast data for predictions (7 days)
                    $dailyForecast = $weatherService->formatDailyForecastForModel($apiData);

                    // Make predictions for current time (today)
                    $currentSample = [
                        $weatherData['temp'],
                        $weatherData['humidity'],
                        $weatherData['wind'],
                        $weatherData['precip'],
                        $weatherData['condition']
                    ];

                    $currentJacket = $jacket->predict([$currentSample]);
                    $currentUmbrella = $umbrella->predict([$currentSample]);

                    $result = [
                        'current' => [
                            'jacket' => $currentJacket[0] ?? 'no',
                            'umbrella' => $currentUmbrella[0] ?? 'no'
                        ],
                        'forecast' => []
                    ];

                    // Make predictions for each day in the forecast
                    if (!empty($dailyForecast)) {
                        foreach ($dailyForecast as $dayData) {
                            $dayJacket = $jacket->predict([$dayData['sample']]);
                            $dayUmbrella = $umbrella->predict([$dayData['sample']]);

                            $result['forecast'][] = [
                                'date' => $dayData['date'],
                                'temp_max' => $dayData['temp_max'],
                                'temp_min' => $dayData['temp_min'],
                                'temp' => $dayData['temp'],
                                'humidity' => $dayData['humidity'],
                                'wind' => $dayData['wind'],
                                'precip' => $dayData['precip'],
                                'precipitation_sum' => $dayData['precipitation_sum'],
                                'condition' => $dayData['condition'],
                                'jacket' => $dayJacket[0] ?? 'no',
                                'umbrella' => $dayUmbrella[0] ?? 'no'
                            ];
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        header('Location: /public/error.php?message=' . urlencode('Prediction failed: ' . $e->getMessage()));
        exit;
    }
} else {
    // If no POST data, redirect to index
    header('Location: /index.php');
    exit;
}
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Weather Results - <?php echo htmlspecialchars($location); ?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <link rel="icon" href="/assets/favicon.ico" type="image/x-icon">

    <style>
        :root {
    --glass-background: rgba(255, 255, 255, 0.12);
    --glass-background-header: rgba(255, 255, 255, 0.22);
    --glass-border: rgba(255, 255, 255, 0.35);
    --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
    --glass-blur: 25px;
    --card-background-needed: rgba(34, 197, 94, 0.15);
    --card-background-not-needed: rgba(244, 67, 54, 0.15);
    --color-green-glow: rgba(34, 197, 94, 0.5);
    --color-red-glow: rgba(244, 67, 54, 0.5);
    --color-hover-glow: rgba(102, 126, 234, 0.4);
}

/* --- Loading Screen Styles (kept) --- */
.loading-screen {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: url(/assets/loading_bg.png);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999;
  transition: opacity 0.5s ease;
}

.loading-content {
  text-align: center;
}

.loading-logo {
  width: 150px;
  height: auto;
  animation: pulse 2s ease-in-out infinite;
  margin-bottom: 30px;
}

.loading-spinner {
  width: 50px;
  height: 50px;
  border: 4px solid #202020;
  border-top: 4px solid #ffffff;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto;
}

@keyframes pulse {
  0%,
  100% {
    transform: scale(1);
    opacity: 1;
  }
  50% {
    transform: scale(1.1);
    opacity: 0.8;
  }
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    /* Keep existing styles */
    font-family: 'Poppins', sans-serif;
    min-height: 100vh;
    position: relative;
    overflow-x: hidden;
    /* ADD: Ensure body fills viewport, which is crucial for 100vh */
    height: 100%;
}

/* Weather Background System */
.weather-background {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: -1;
    transition: opacity 0.8s ease-in-out;
}

.weather-background::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(0, 0, 0, 0.3) 0%, rgba(0, 0, 0, 0.2) 100%);
    z-index: 1;
}

.weather-background.sunny {
    background-image: url("/assets/hero2.png");
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    min-height: 100vh;
}

.weather-background.cloudy {
    background-image: url("/assets/hero3.png");
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    min-height: 100vh;
}

.weather-background.rainy {
    background-image: url("/assets/hero5.png");
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    min-height: 100vh;
}

.weather-background.snowy {
    background-image: url("/assets/hero1.png");
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    min-height: 100vh;
}

/* Main Layout Container */
.main-layout {
    display: grid;
    grid-template-columns: 0.8fr 1.2fr;
    /* Enforce full viewport height and prevent outer scroll */
    height: 100vh;
    overflow: hidden;
    position: relative;
    z-index: 1;
}

/* Left Panel - Hero Weather Display */
.left-panel {
    padding: 40px;
    display: flex;
    flex-direction: column;
    color: white;
    position: relative;
    /* Change: Use 100% to inherit 100vh from .main-layout */
    height: 100%;
    /* Ensure no scrolling on the left panel */
    overflow: hidden; 
}

.left-panel img{
    width: 140px;
    margin-bottom: 40px;
    filter: drop-shadow(0 8px 24px rgba(0, 0, 0, 0.3));
}

.main-weather-display {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.temperature-display {
    font-size: 140px;
    font-weight: 800;
    line-height: 1;
    margin-bottom: 10px;
    text-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
}

.location-display {
    font-size: 42px;
    font-weight: 600;
    margin-bottom: 8px;
    text-shadow: 0 2px 12px rgba(0, 0, 0, 0.3);
}

.date-time-display {
    font-size: 16px;
    opacity: 0.95;
    margin-bottom: 40px;
    text-shadow: 0 1px 6px rgba(0, 0, 0, 0.3);
}

.weather-condition-display {
    font-size: 50px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-bottom: 30px;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.forecast-preview {
    display: flex;
    gap: 20px;
    margin-top: 40px;
}

.forecast-preview-item {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(25px);
    -webkit-backdrop-filter: blur(25px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 20px;
    padding: 20px 28px;
    text-align: center;
    min-width: 100px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15), inset 0 1px 0 rgba(255, 255, 255, 0.2);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.forecast-preview-item:hover {
    transform: translateY(-6px) scale(1.05);
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.25);
    border-color: rgba(255, 255, 255, 0.4);
}

.forecast-preview-item .time {
    font-size: 14px;
    opacity: 0.95;
    margin-bottom: 8px;
    text-shadow: 0 1px 4px rgba(0, 0, 0, 0.2);
}

.forecast-preview-item .temp {
    font-size: 20px;
    font-weight: 700;
    text-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
}

/* Right Panel - Details and Predictions */
.right-panel {
    background: whitesmoke;
    overflow-y: auto; 
    height: 100%; 
    border-left: 1px solid #e0e0e0;
    box-shadow: -8px 0 32px rgba(0, 0, 0, 0.05);
}

.right-panel-header {
    padding: 20px 40px;
    background: whitesmoke;
    backdrop-filter: blur(var(--glass-blur));
    -webkit-backdrop-filter: blur(var(--glass-blur));
    border-bottom: 1px solid var(--glass-border);
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.search-form {
    display: flex;
    gap: 12px;
    align-items: center;
}

.autocomplete-container {
    position: relative;
    flex: 1;
}

#citySearch {
    width: 100%;
    padding: 14px 20px;
    border: 2px solid rgba(255, 255, 255, 0.4);
    background: rgba(255, 255, 255, 0.35);
    backdrop-filter: blur(var(--glass-blur));
    -webkit-backdrop-filter: blur(var(--glass-blur));
    border-radius: 25px;
    font-size: 15px;
    font-family: 'Poppins', sans-serif;
    transition: all 0.3s ease;
    color: #1a1a1a;
    font-weight: 500;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

#citySearch::placeholder {
    color: rgba(0, 0, 0, 0.5);
    font-weight: 500;
}

#citySearch:focus {
    outline: none;
    border-color: rgba(255, 255, 255, 0.6);
    box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3), 0 4px 16px rgba(0, 0, 0, 0.12);
    background: rgba(255, 255, 255, 0.5);
    color: #1a1a1a;
}

#citySearch:focus::placeholder {
    color: rgba(0, 0, 0, 0.4);
}

.search-button {
    padding: 14px 28px;
    background: rgba(32, 32, 32, 1);
    backdrop-filter: blur(var(--glass-blur));
    -webkit-backdrop-filter: blur(var(--glass-blur));
    border: 1px solid var(--glass-border);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    color: #ffffffff;
    border-radius: 25px;
    cursor: pointer;
    font-size: 16px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.search-button:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
}

.autocomplete-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(0, 0, 0, 0.08);
    border-radius: 16px;
    margin-top: 8px;
    max-height: 300px;
    overflow-y: auto;
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.15);
    display: none;
    z-index: 1000;
}

.autocomplete-dropdown.show {
    display: block;
}

.autocomplete-item {
    padding: 12px 20px;
    cursor: pointer;
    transition: all 0.2s ease;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.autocomplete-item:last-child {
    border-bottom: none;
}

.autocomplete-item:hover {
    background: linear-gradient(135deg, #f8f9ff 0%, #f0f2ff 100%);
}

.city-name {
    font-weight: 600;
    color: #1a1a1a;
    margin-bottom: 4px;
}

.city-details {
    font-size: 13px;
    color: #666;
}

.right-panel-content {
    padding: 40px;
}

/* Weather Details Grid */
.weather-details-section {
    margin-bottom: 40px;
}

.section-title {
    font-size: 20px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.section-title i {
    color: #1a1a1a;
}

.weather-details-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.detail-card {
    background: white;
    border: 1px solid #e0e0e0;
    color: #1a1a1a;
    padding: 28px;
    border-radius: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.2);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.detail-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    transition: left 0.5s ease;
}

.detail-card:hover::before {
    left: 100%;
}

.detail-card:hover {
    transform: translateY(-6px) scale(1.02);
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.25);
    border-color: rgba(255, 255, 255, 0.4);
}

.detail-card i {
    font-size: 32px;
    transition: transform 0.3s ease;
}

.detail-card:hover i {
    transform: scale(1.15) rotate(5deg);
}

.detail-label {
    font-size: 14px;
    opacity: 1;
    margin-bottom: 6px;
    font-weight: 500;
}

.detail-value {
    font-size: 26px;
    font-weight: 700;
}

/* Predictions Section */
.predictions-section {
    margin-bottom: 40px;
}

.prediction-cards {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.prediction-card {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(25px);
    -webkit-backdrop-filter: blur(25px);
    border: 2px solid var(--glass-border);
    border-radius: 24px;
    padding: 36px;
    text-align: center;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.2);
    position: relative;
    overflow: hidden;
}

.prediction-card::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
    opacity: 0;
    transition: opacity 0.4s ease;
}

.prediction-card:hover::after {
    opacity: 1;
}

.prediction-card:hover {
    transform: translateY(-8px) scale(1.03);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.3);
}

.prediction-card.needed {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.25) 0%, rgba(34, 197, 94, 0.15) 100%);
    border-color: rgba(34, 197, 94, 0.9);
    box-shadow: 0 8px 24px rgba(34, 197, 94, 0.35), 0 0 40px rgba(34, 197, 94, 0.2);
}

.prediction-card.needed:hover {
    box-shadow: 0 20px 50px rgba(34, 197, 94, 0.5), 0 0 60px rgba(34, 197, 94, 0.3);
    border-color: rgba(34, 197, 94, 1);
}

.prediction-card.not-needed {
    background: linear-gradient(135deg, rgba(244, 67, 54, 0.25) 0%, rgba(244, 67, 54, 0.15) 100%);
    border-color: rgba(244, 67, 54, 0.9);
    box-shadow: 0 8px 24px rgba(244, 67, 54, 0.35), 0 0 40px rgba(244, 67, 54, 0.2);
}

.prediction-card.not-needed:hover {
    box-shadow: 0 20px 50px rgba(244, 67, 54, 0.5), 0 0 60px rgba(244, 67, 54, 0.3);
    border-color: rgba(244, 67, 54, 1);
}

.prediction-icon {
    font-size: 56px;
    margin-bottom: 16px;
    transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.prediction-card:hover .prediction-icon {
    transform: scale(1.2) rotate(-5deg);
}

.prediction-card.needed .prediction-icon {
    color: #22c55e;
}

.prediction-card.not-needed .prediction-icon {
    color: #ef4444;
}

.prediction-card h3 {
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 12px;
    color: #1a1a1a;
}

.prediction-card p {
    font-size: 15px;
    color: #333;
    opacity: 1;
    line-height: 1.5;
}

/* Forecast Section */
.forecast-section {
    margin-top: 40px;
}

.chart-container {
    background: rgba(255, 255, 255, 0.22);
    backdrop-filter: blur(var(--glass-blur));
    -webkit-backdrop-filter: blur(var(--glass-blur));
    border-radius: 24px;
    padding: 36px;
    margin-bottom: 30px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.2);
    border: 1px solid var(--glass-border);
}

.forecast-table-wrapper {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(var(--glass-blur));
    -webkit-backdrop-filter: blur(var(--glass-blur));
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.2);
    border: 1px solid var(--glass-border);
}

.forecast-table-wrapper table {
    width: 100%;
    border-collapse: collapse;
}

.forecast-table-wrapper th {
    padding: 18px;
    border-bottom: 1px solid #e0e0e0;
    background-color: #f5f5f5;
    color: #1a1a1a;
    font-weight: 600;
    text-align: left;
}

.forecast-table-wrapper td {
    padding: 18px;
    border-bottom: 1px solid #e0e0e0;
    color: #1a1a1a;
    font-weight: 500;
}

.forecast-table-wrapper tbody tr[style*="background"] {
    background: rgba(255, 249, 230, 0.25) !important;
}

.forecast-table-wrapper tbody tr:hover {
    background: rgba(255, 255, 255, 0.2);
    transition: background 0.3s ease;
}

.summary {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-left: 4px solid rgba(255, 255, 255, 0.5);
    border-radius: 16px;
    padding: 24px;
    margin-top: 24px;
    color: #202020;
    font-size: 15px;
    line-height: 1.6;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
}

.summary strong {
    color: white;
    font-weight: 700;
}

.error {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.25) 0%, rgba(220, 38, 38, 0.2) 100%);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    color: white;
    padding: 24px;
    border-radius: 20px;
    border: 1px solid rgba(239, 68, 68, 0.5);
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 8px 24px rgba(239, 68, 68, 0.3);
}

.error i {
    font-size: 28px;
    flex-shrink: 0;
}

.error-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    gap: 20px;
}

.error-message {
    display: flex;
    align-items: center;
    gap: 16px;
    flex: 1;
}

.error-button {
    background: rgba(255, 255, 255, 0.3);
    color: white;
    padding: 12px 24px;
    border-radius: 20px;
    text-decoration: none;
    font-weight: 600;
    white-space: nowrap;
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.4);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 14px;
    flex-shrink: 0;
}

.error-button:hover {
    background: rgba(255, 255, 255, 0.5);
    transform: translateY(-2px);
    border-color: rgba(255, 255, 255, 0.6);
}

/* Responsive Design */
@media (max-width: 1200px) {
    .main-layout {
        grid-template-columns: 1fr;
    }

    .left-panel {
        min-height: 60vh;
    }

    .temperature-display {
        font-size: 100px;
    }

    .location-display {
        font-size: 32px;
    }
}

@media (max-width: 768px) {
    .left-panel,
    .right-panel-header,
    .right-panel-content {
        padding: 20px;
    }

    .temperature-display {
        font-size: 80px;
    }

    .location-display {
        font-size: 28px;
    }

    .weather-details-grid,
    .prediction-cards {
        grid-template-columns: 1fr;
    }

    .forecast-preview {
        flex-wrap: wrap;
    }

    .right-panel-header {
        padding: 12px 20px;
    }

    .search-form {
        flex-direction: column;
        gap: 10px;
    }

    .autocomplete-container {
        width: 100%;
    }

    .search-button {
        width: 100%;
    }

    #citySearch {
        padding: 12px 16px;
        font-size: 14px;
    }

    .forecast-table-wrapper {
        overflow-x: auto;
    }

    .forecast-table-wrapper table {
        font-size: 13px;
    }

    .forecast-table-wrapper th,
    .forecast-table-wrapper td {
        padding: 12px 8px;
    }
}

@media (max-width: 480px) {
    .main-layout {
        height: auto;
        overflow: visible;
        grid-template-columns: 1fr;
    }

    .left-panel {
        min-height: auto;
        overflow: visible;
        padding: 16px;
        height: auto;
    }

    .right-panel {
        overflow: visible;
        height: auto;
        border-left: none;
        border-top: 1px solid #e0e0e0;
    }

    .right-panel-header {
        padding: 16px;
        position: relative;
        box-shadow: none;
        border-bottom: 1px solid #e0e0e0;
    }

    .right-panel-content {
        padding: 16px;
    }

    .temperature-display {
        font-size: 64px;
    }

    .location-display {
        font-size: 24px;
    }

    .weather-condition-display {
        font-size: 18px;
    }

    .date-time-display {
        font-size: 14px;
    }

    .forecast-preview {
        gap: 10px;
        margin-top: 20px;
    }

    .forecast-preview-item {
        min-width: 80px;
        padding: 12px 16px;
        border-radius: 12px;
    }

    .section-title {
        font-size: 18px;
        margin-bottom: 16px;
    }

    .weather-details-grid {
        gap: 12px;
    }

    .detail-card {
        padding: 16px;
        gap: 12px;
    }

    .detail-card i {
        font-size: 24px;
    }

    .detail-label {
        font-size: 12px;
    }

    .detail-value {
        font-size: 20px;
    }

    .prediction-cards {
        gap: 12px;
    }

    .prediction-card {
        padding: 20px;
    }

    .prediction-icon {
        font-size: 40px;
        margin-bottom: 12px;
    }

    .prediction-card h3 {
        font-size: 18px;
    }

    .prediction-card p {
        font-size: 14px;
    }

    .chart-container {
        padding: 20px;
        margin-bottom: 20px;
    }

    .forecast-table-wrapper table {
        font-size: 12px;
    }

    .forecast-table-wrapper th,
    .forecast-table-wrapper td {
        padding: 10px 6px;
    }

    .summary {
        padding: 16px;
        font-size: 13px;
        margin-top: 16px;
    }
}
    </style>
</head>

<body>
    <div id="loading-screen" class="loading-screen">
        <div class="loading-content">
            <img src="/assets/umaaraw.png" alt="Loading" class="loading-logo">
            <div class="loading-spinner"></div>
        </div>
    </div>

    <!-- Dynamic Weather Background -->
    <div id="weatherBackground" class="weather-background"></div>

    <div id="main-content" style="display:none;">
        <?php if ($weatherData && $result): ?>
            <div class="main-layout">
                <!-- Left Panel - Hero Weather Display -->
                <div class="left-panel">
                    <a href="/index.php"><img src="/assets/umaaraw.png" alt="Loading"></a>

                    <div class="main-weather-display">
                        <div class="temperature-display">
                            <?php echo $weatherData['temp']; ?>°
                        </div>
                        <div class="location-display">
                            <?php echo htmlspecialchars($location); ?>
                        </div>
                        <div class="date-time-display">
                            <?php
                            $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                            $monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                            $today = new DateTime();
                            $currentTime = $today->format('H:i');
                            echo $currentTime . ' - ' . $dayNames[$today->format('w')] . ', ' . $today->format('d') . ' ' . $monthNames[$today->format('n') - 1] . " '" . $today->format('y');
                            ?>
                        </div>
                        <div class="weather-condition-display">
                            <?php
                            $conditions = ['Sunny', 'Cloudy', 'Rain', 'Snow'];
                            echo $conditions[$weatherData['condition']] ?? 'Unknown';
                            ?>
                        </div>

                        <?php if (!empty($result['forecast']) && count($result['forecast']) >= 3): ?>
                            <div class="forecast-preview">
                                <?php for ($i = 0; $i < 3 && $i < count($result['forecast']); $i++): 
                                    $forecast = $result['forecast'][$i];
                                    $forecastDate = new DateTime($forecast['date']);
                                    $timeLabel = $i === 0 ? 'Today' : $forecastDate->format('D');
                                ?>
                                    <div class="forecast-preview-item">
                                        <div class="time"><?php echo $timeLabel; ?></div>
                                        <div class="temp"><?php echo round($forecast['temp']); ?>°</div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Panel - Details and Predictions -->
                <div class="right-panel">
                    <div class="right-panel-header">
                        <form method="post" action="results.php" class="search-form">
                            <div class="autocomplete-container">
                                <input type="text" id="citySearch" name="location" required placeholder="Search Location..." value="<?php echo htmlspecialchars($location); ?>" autocomplete="off">
                                <div id="autocompleteDropdown" class="autocomplete-dropdown"></div>
                            </div>
                            <button type="submit" class="search-button">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>

                    <div class="right-panel-content">
                        <?php if ($error): ?>
                            <div class="error">
                                <div class="error-content">
                                    <div class="error-message">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <span><?php echo $error; ?></span>
                                    </div>
                                    <a href="/index.php" class="error-button">
                                        <i class="fas fa-home"></i> Go Home
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Weather Details -->
                        <div class="weather-details-section">
                            <h2 class="section-title"><i class="fas fa-info-circle"></i> Weather Details</h2>
                            <div class="weather-details-grid">
                                <div class="detail-card">
                                    <i class="fas fa-thermometer-half"></i>
                                    <div class="detail-card-content">
                                        <div class="detail-label">Temp max</div>
                                        <div class="detail-value"><?php echo !empty($result['forecast']) ? $result['forecast'][0]['temp_max'] : $weatherData['temp']; ?>°</div>
                                    </div>
                                </div>
                                <div class="detail-card">
                                    <i class="fas fa-thermometer-quarter"></i>
                                    <div class="detail-card-content">
                                        <div class="detail-label">Temp min</div>
                                        <div class="detail-value"><?php echo !empty($result['forecast']) ? $result['forecast'][0]['temp_min'] : $weatherData['temp']; ?>°</div>
                                    </div>
                                </div>
                                <div class="detail-card">
                                    <i class="fas fa-tint"></i>
                                    <div class="detail-card-content">
                                        <div class="detail-label">Humidity</div>
                                        <div class="detail-value"><?php echo $weatherData['humidity']; ?>%</div>
                                    </div>
                                </div>
                                <div class="detail-card">
                                    <i class="fas fa-cloud"></i>
                                    <div class="detail-card-content">
                                        <div class="detail-label">Cloudy</div>
                                        <div class="detail-value"><?php echo $weatherData['precip']; ?>%</div>
                                    </div>
                                </div>
                                <div class="detail-card">
                                    <i class="fas fa-wind"></i>
                                    <div class="detail-card-content">
                                        <div class="detail-label">Wind</div>
                                        <div class="detail-value"><?php echo $weatherData['wind']; ?> km/h</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Predictions -->
                        <?php
                        $todayForecast = null;
                        if (!empty($result['forecast'])) {
                            $today = new DateTime();
                            foreach ($result['forecast'] as $forecast) {
                                $forecastDate = new DateTime($forecast['date']);
                                if ($forecastDate->format('Y-m-d') === $today->format('Y-m-d')) {
                                    $todayForecast = $forecast;
                                    break;
                                }
                            }
                            if (!$todayForecast && !empty($result['forecast'])) {
                                $todayForecast = $result['forecast'][0];
                            }
                        }
                        $displayJacket = $todayForecast ? $todayForecast['jacket'] : $result['current']['jacket'];
                        $displayUmbrella = $todayForecast ? $todayForecast['umbrella'] : $result['current']['umbrella'];
                        ?>

                        <div class="predictions-section">
                            <h2 class="section-title"><i class="fas fa-lightbulb"></i> Today's Weather Forecast</h2>
                            <div class="prediction-cards">
                                <div class="prediction-card <?php echo $displayJacket === 'yes' ? 'needed' : 'not-needed'; ?>">
                                    <div class="prediction-icon">
                                        <?php if ($displayJacket === 'yes'): ?>
                                            <i class="fas fa-check-circle"></i>
                                        <?php else: ?>
                                            <i class="fas fa-times-circle"></i>
                                        <?php endif; ?>
                                    </div>
                                    <h3>Jacket</h3>
                                    <p><?php echo $displayJacket === 'yes' ? "Don't forget your jacket!" : "You don't have to, but it's your choice!"; ?></p>
                                </div>

                                <div class="prediction-card <?php echo $displayUmbrella === 'yes' ? 'needed' : 'not-needed'; ?>">
                                    <div class="prediction-icon">
                                        <?php if ($displayUmbrella === 'yes'): ?>
                                            <i class="fas fa-umbrella"></i>
                                        <?php else: ?>
                                            <i class="fas fa-times-circle"></i>
                                        <?php endif; ?>
                                    </div>
                                    <h3>Umbrella</h3>
                                    <p><?php echo $displayUmbrella === 'yes' ? 'Bring an umbrella!' : "You don't have to, but it's your choice!"; ?></p>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($result['forecast'])): ?>
                            <div class="forecast-section">
                                <h2 class="section-title"><i class="fas fa-chart-line"></i> 7-Day Forecast</h2>

                                <div class="chart-container">
                                    <div class="chart-wrapper">
                                        <canvas id="tempChart"></canvas>
                                    </div>
                                </div>

                                <div class="forecast-table-wrapper">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Day</th>
                                                <th style="text-align: center;">Temp</th>
                                                <th style="text-align: center;">Precip</th>
                                                <th style="text-align: center;">Jacket</th>
                                                <th style="text-align: center;">Umbrella</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $conditions = ['Sunny', 'Cloudy', 'Rain', 'Snow'];
                                            $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                                            foreach ($result['forecast'] as $forecast):
                                                $date = new DateTime($forecast['date']);
                                                $dayName = $dayNames[$date->format('w')];
                                                $dateStr = $date->format('M d');
                                                $isToday = $date->format('Y-m-d') === date('Y-m-d');
                                            ?>
                                                <tr style="<?php echo $isToday ? 'background: #fff9e6;' : ''; ?>">
                                                    <td style="font-weight: <?php echo $isToday ? '600' : 'normal'; ?>;">
                                                        <?php echo htmlspecialchars($dateStr); ?>
                                                    </td>
                                                    <td style="color: #000000ff;">
                                                        <?php echo $dayName; ?>
                                                    </td>
                                                    <td style="text-align: center; font-weight: 600;">
                                                        <?php echo $forecast['temp_max']; ?>° / <?php echo $forecast['temp_min']; ?>°
                                                    </td>
                                                    <td style="text-align: center;">
                                                        <?php
                                                        if ($forecast['precipitation_sum'] > 0) {
                                                            echo round($forecast['precipitation_sum'], 1) . 'mm';
                                                        } else {
                                                            echo $forecast['precip'] . '%';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td style="text-align: center; font-size: 1.3em;">
                                                        <?php echo $forecast['jacket'] === 'yes' ? '<i class="fas fa-check-circle" style="color: #4caf50;"></i>' : '<i class="fas fa-times-circle" style="color: #f44336;"></i>'; ?>
                                                    </td>
                                                    <td style="text-align: center; font-size: 1.3em;">
                                                        <?php echo $forecast['umbrella'] === 'yes' ? '<i class="fas fa-umbrella" style="color: #2196f3;"></i>' : '<i class="fas fa-times-circle" style="color: #f44336;"></i>'; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="summary">
                                    <strong><i class="fas fa-clipboard-list"></i> Summary:</strong>
                                    <?php
                                    $jacketDays = [];
                                    $umbrellaDays = [];
                                    foreach ($result['forecast'] as $idx => $f) {
                                        if ($f['jacket'] === 'yes') $jacketDays[] = $idx + 1;
                                        if ($f['umbrella'] === 'yes') $umbrellaDays[] = $idx + 1;
                                    }
                                    $jacketText = count($jacketDays) > 0 ? 'Days ' . implode(', ', $jacketDays) : 'None';
                                    $umbrellaText = count($umbrellaDays) > 0 ? 'Days ' . implode(', ', $umbrellaDays) : 'None';
                                    echo "Jacket needed: $jacketText | Umbrella needed: $umbrellaText";
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div style="padding: 40px; text-align: center;">
                <div class="error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?php echo $error ?: 'Unable to load weather data'; ?></span>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // ============================================
        // WEATHER BACKGROUND HANDLER
        // ============================================
        function setWeatherBackground(condition) {
            const background = document.getElementById('weatherBackground');
            if (!background) return;

            // Remove all weather classes
            background.classList.remove('sunny', 'cloudy', 'rainy', 'snowy');

            // Map condition to background class
            const conditionMap = {
                0: 'sunny',    // Sunny
                1: 'cloudy',   // Cloudy
                2: 'rainy',    // Rain
                3: 'snowy'     // Snow
            };

            // Handle both numeric and string conditions
            let weatherClass = 'sunny'; // Default fallback
            
            if (typeof condition === 'number') {
                weatherClass = conditionMap[condition] || 'sunny';
            } else if (typeof condition === 'string') {
                const lowerCondition = condition.toLowerCase();
                if (lowerCondition.includes('sun') || lowerCondition.includes('clear')) {
                    weatherClass = 'sunny';
                } else if (lowerCondition.includes('cloud') || lowerCondition.includes('overcast')) {
                    weatherClass = 'cloudy';
                } else if (lowerCondition.includes('rain') || lowerCondition.includes('drizzle')) {
                    weatherClass = 'rainy';
                } else if (lowerCondition.includes('snow') || lowerCondition.includes('sleet')) {
                    weatherClass = 'snowy';
                }
            }

            background.classList.add(weatherClass);
        }

        // Initialize weather background on page load
        <?php if ($weatherData): ?>
            setWeatherBackground(<?php echo $weatherData['condition']; ?>);
        <?php endif; ?>

        // ============================================
        // CHART.JS CONFIGURATION
        // ============================================
        <?php if (!empty($result['forecast'])): ?>
        const forecastData = <?php echo json_encode($result['forecast']); ?>;

        const ctx = document.getElementById('tempChart').getContext('2d');
        const labels = forecastData.map(f => {
            const date = new Date(f.date);
            return date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric'
            });
        });

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                        label: 'High Temp (°C)',
                        data: forecastData.map(f => f.temp_max),
                        backgroundColor: 'rgba(255, 99, 132, 0.85)',
                        borderColor: 'rgb(255, 99, 132)',
                        borderWidth: 2,
                        borderRadius: 10
                    },
                    {
                        label: 'Low Temp (°C)',
                        data: forecastData.map(f => f.temp_min),
                        backgroundColor: 'rgba(54, 162, 235, 0.85)',
                        borderColor: 'rgb(54, 162, 235)',
                        borderWidth: 2,
                        borderRadius: 10
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            font: {
                                size: 13,
                                weight: '600',
                                family: 'Poppins'
                            },
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.85)',
                        padding: 14,
                        titleFont: {
                            size: 14,
                            family: 'Poppins'
                        },
                        bodyFont: {
                            size: 13,
                            family: 'Poppins'
                        },
                        cornerRadius: 8
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        title: {
                            display: true,
                            text: 'Temperature (°C)',
                            font: {
                                size: 14,
                                weight: '600',
                                family: 'Poppins'
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.04)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date',
                            font: {
                                size: 14,
                                weight: '600',
                                family: 'Poppins'
                            }
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // ============================================
        // CITY AUTOCOMPLETE FUNCTIONALITY
        // ============================================
        let debounceTimer;
        const citySearch = document.getElementById('citySearch');
        const autocompleteDropdown = document.getElementById('autocompleteDropdown');

        if (citySearch && autocompleteDropdown) {
            citySearch.addEventListener('input', function() {
                const query = this.value.trim();

                clearTimeout(debounceTimer);

                if (query.length < 2) {
                    autocompleteDropdown.classList.remove('show');
                    autocompleteDropdown.innerHTML = '';
                    return;
                }

                debounceTimer = setTimeout(() => searchCities(query), 300);
            });

            document.addEventListener('click', function(e) {
                if (!e.target.closest('.autocomplete-container')) {
                    autocompleteDropdown.classList.remove('show');
                }
            });
        }

        async function searchCities(query) {
            try {
                const response = await fetch(
                    `https://geocoding-api.open-meteo.com/v1/search?name=${encodeURIComponent(query)}&count=10&language=en&format=json`
                );

                if (!response.ok) throw new Error('Failed to fetch cities');

                const data = await response.json();

                if (!data.results || data.results.length === 0) {
                    autocompleteDropdown.innerHTML = '<div class="autocomplete-item">No cities found</div>';
                    autocompleteDropdown.classList.add('show');
                    return;
                }

                displayCityResults(data.results);
            } catch (error) {
                console.error('Error searching cities:', error);
                autocompleteDropdown.innerHTML = '<div class="autocomplete-item">Error loading cities</div>';
                autocompleteDropdown.classList.add('show');
            }
        }

        function displayCityResults(cities) {
            autocompleteDropdown.innerHTML = '';

            cities.forEach(city => {
                const item = document.createElement('div');
                item.className = 'autocomplete-item';

                const cityName = document.createElement('div');
                cityName.className = 'city-name';
                cityName.textContent = city.name;

                const cityDetails = document.createElement('div');
                cityDetails.className = 'city-details';
                const parts = [];
                if (city.admin1) parts.push(city.admin1);
                if (city.country) parts.push(city.country);
                cityDetails.textContent = parts.join(', ');

                item.appendChild(cityName);
                item.appendChild(cityDetails);

                item.addEventListener('click', () => selectCity(city));

                autocompleteDropdown.appendChild(item);
            });

            autocompleteDropdown.classList.add('show');
        }

        function selectCity(city) {
            citySearch.value = `${city.name}, ${city.country}`;
            autocompleteDropdown.classList.remove('show');
        }

        // ============================================
        // LOADING SCREEN HANDLER
        // ============================================
        window.addEventListener('load', function() {
            setTimeout(function() {
                const loadingScreen = document.getElementById('loading-screen');
                const mainContent = document.getElementById('main-content');
                if (loadingScreen && mainContent) {
                    loadingScreen.style.opacity = '0';
                    setTimeout(function() {
                        loadingScreen.style.display = 'none';
                        mainContent.style.display = 'block';
                    }, 500);
                }
            }, 1000);
        });
    </script>
</body>

</html>
