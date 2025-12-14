<?php
?>
<!doctype html>
<html>

<head>
  <meta charset="utf-8">
  <title>Jacket o Payong? - Search</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Google+Sans+Flex:opsz,wght@6..144,1..1000&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="styles.css">
  <link rel="icon" href="/assets/favicon.ico" type="image/x-icon">
</head>

<body>
  <div id="loading-screen" class="loading-screen">
    <div class="loading-content">
      <img src="/assets/umaaraw.png" alt="Loading" class="loading-logo">
    </div>
  </div>

  <div class="bg-hero">

    <!-- Background slideshow -->
    <div class="hero hero1"></div>
    <div class="hero hero2"></div>
    <div class="hero hero3"></div>
    <div class="hero hero4"></div>
    <div class="hero hero5"></div>

    <!-- Your existing content -->
    <div class="container" id="main-content" style="display: none;">
      <div class="container">

        <div class="header">
          <a href="index.php">
            <img src="/assets/index-logo.png" alt="index-logo">
          </a>
        </div>

        <div class="card-bg">
          <form method="post" action="results.php">
            <div class="autocomplete-container">
              <input type="text" id="citySearch" name="location" required
                placeholder=" Start typing city you want to select... (e.g., Manila, San Pablo, Nagcarlan)"
                autocomplete="off">
              <div id="autocompleteDropdown" class="autocomplete-dropdown"></div>
            </div>
            <button type="submit" class="main-button">
              <i class="fas fa-bolt"></i> Get Weather Prediction
            </button>
          </form>
        </div>
      </div>
    </div>

  </div>


  <script>
    let debounceTimer;
    const citySearch = document.getElementById('citySearch');
    const autocompleteDropdown = document.getElementById('autocompleteDropdown');

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

    // Hide loading screen when page is loaded
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
      }, 1000); // Show loading for at least 1 second
    });
  </script>
</body>

</html>