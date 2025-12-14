<?php
// config.php - Weather API Configuration (Open-Meteo)

// Open-Meteo API - No API key required! Free and open-source.
// Documentation: https://open-meteo.com/en/docs

// Geocoding API endpoint (converts city names to coordinates)
define('GEOCODING_API_URL', 'https://geocoding-api.open-meteo.com/v1/search');

// Weather Forecast API endpoint
define('FORECAST_API_URL', 'https://api.open-meteo.com/v1/forecast');
