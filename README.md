Vessel Scraper
========================

Crawl marine websites to scrape vessel information, including IMO Code, MMSI, Vessel Name, Flag Code and Vessel Type

### Clone the git Repository

Run the following command in your htdocs directory:

```
git clone https://github.com/djandyr/vessel-scraper.git
```    

### Install Dependencies

```
php composer.phar install

### Run Scraper

```
php console.php scrape:vessels

A `vessel.csv` will be created in the root directory.
