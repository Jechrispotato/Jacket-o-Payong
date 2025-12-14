# Weather API Setup Guide - Open-Meteo

## 🎉 No API Key Required!

This application uses [Open-Meteo](https://open-meteo.com/), a free and open-source weather API that doesn't require any API key, registration, or credit card!

## What is Open-Meteo?

Open-Meteo is an open-source weather API that:
- ✅ **Free** for non-commercial use
- ✅ **No API key** required
- ✅ **No registration** needed
- ✅ **No credit card** required
- ✅ Uses data from national weather services
- ✅ High-resolution forecasts (1-11 km)
- ✅ Updated hourly

## How It Works

The application uses two Open-Meteo APIs:

1. **Geocoding API**: Converts city names to coordinates
   - Endpoint: `https://geocoding-api.open-meteo.com/v1/search`
   
2. **Forecast API**: Gets current weather data
   - Endpoint: `https://api.open-meteo.com/v1/forecast`

## Setup

**You don't need to do anything!** The API is already configured and ready to use.

If you want to customize the endpoints, you can edit `config.php`, but the defaults work perfectly.

## Usage Limits

Open-Meteo has no strict access restrictions, but they encourage:
- Fair usage for non-commercial purposes
- Consider API subscription if you need > 10,000 calls/day for commercial use

## More Information

- Official website: https://open-meteo.com/
- API Documentation: https://open-meteo.com/en/docs
- GitHub: https://github.com/open-meteo/open-meteo
- License: AGPLv3 (open-source)

## Troubleshooting

If you encounter issues:

1. **City not found**: Try being more specific (e.g., "Manila, Philippines" instead of just "Manila")
2. **Network errors**: Check your internet connection
3. **API errors**: Open-Meteo is usually very reliable, but check their status page if issues persist

## Credits

This application uses Open-Meteo weather data. Please credit Open-Meteo if you use this in your projects.
