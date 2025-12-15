# Weather-Based Clothing Suggestion (PHP + php-ml)


## Requirements
- Youtube Link: https://youtu.be/Nfjs_hEl7ZA
- Presentation Link: https://www.canva.com/design/DAG7flvqu6U/EtGPr6IpR5Leisp1_3zHXQ/view?utm_content=DAG7flvqu6U&utm_campaign=designshare&utm_medium=link2&utm_source=uniquelinks&utlId=h7d27f2b125
- Live Web Application via InfinityFree: https://jacketopayong.xo.je

  
## Requirements
- PHP >= 7.4
- Composer
- cURL extension (usually enabled by default)
- **No API key required!** Uses [Open-Meteo](https://open-meteo.com/) - free and open-source

## Setup
1. `composer install`
2. (Optional) Replace `src/dataset.csv` with your CSV of labeled samples.
3. Train models: `php src/train.php`
4. Serve site: `php -S localhost:8000 -t public`
5. Open `http://localhost:8000`

That's it! No API key configuration needed. 🎉

## Features
- Real-time weather data integration via [Open-Meteo API](https://open-meteo.com/) (free, no API key required)
- Enter city name to get weather-based clothing suggestions
- Machine learning models predict when to bring a jacket and/or umbrella
- Automatic geocoding (city name to coordinates)

## Notes
- For production use, collect a larger labeled dataset and expand features (feels_like, time_of_day).
- `public/retrain.php` allows admin upload of CSV to retrain models.
- Open-Meteo is free for non-commercial use with no strict access restrictions.
- Powered by open-source weather data from national weather services.
