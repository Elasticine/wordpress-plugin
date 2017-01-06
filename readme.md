## Synopsis

This repository holds code for the Elasticine wordpress plugin and examples of usage. The Elasticine wordpress plugin can be used to integrate agency websites with our public API for automatic updating of information from the management system. 

This plugin:
* Automatically pulls artist information from the "Public Website" section of artist set-up on Elasticine, as long as they are set to display publically on that page. 
* Automatically pulls announced and contracted shows for each artist on the company's roster, as long as they are set to display publically. 
* Maintains a set of categories, one for each public artist, as sub-categories of "artist", so that news posts can be associated with individual artists. 

Artist data is set by agents in the internal Elasticine platform artist setup pages. 

Artists have the following website-specific information that may be set (but may also be empty):
* A tagline -- a brief description of the artist
* A URL slug
* A biography, including HTML markup
* Info, including HTML markup -- for example, listing territories the artist plays in and information regarding logistics
* A set of links, with link text, a URL, a type (= web, soundcloud, facebook, or youtube), and a "featured" flag. The intention is that featured links can be displayed more prominently, or include inline media players. 
* A set of press assets, which will typically be images. Includes flags for display as "image" or "link", and a "featured" flag for whether the press asset should be placed more prominently. 
* A set of label names that the artist has released on. 

## Code Examples

### Retrieve all artists on roster
```php

<?php 
/**** DATA GATHERING CODE ELASTICINE **/

	do_action('elasticine_artists');
	$artists = json_decode(get_transient('elasticine_artists'));

/**** END DATA GATHERING CODE **/ 

	var_dump($artists);
?>

```

### Retrieve data on a single artist

```php
/**** ELASTICINE DATA GATHERING CODE **/
$url = $_SERVER['REQUEST_URI'];

//Assumes artist name slug is final part of URL
$end = explode('/', rtrim($url, '/'));
$slug = end($end);

do_action('elasticine_artist',$slug);

$artist = json_decode(get_transient('elasticine_artist_'.$slug));

//Get the next set of shows for this artist
//We'll display the next month worth of shows  on the artist page
$startDate = date('Y-m-d');
$endDate = date('Y-m-d', strtotime("+3 MONTH"));
do_action('elasticine_shows_artist', $slug, $startDate, $endDate);
$shows = json_decode(get_transient('es_'.$slug. "_" . $startDate . "_" . $endDate));
/****END DATA GATHERING CODE **/
var_dump($artist);
var_dump($shows);
```

### Retrieve shows data

```php
/**** DATA GATHERING CODE **/

//Set startDate and endDate URL parameters to constrain date range of shows, using format Y-m-d. 

//if no start date set, make it today
$startDate = (array_key_exists('startDate', $_GET)) ? $_GET['startDate'] : date('Y-m-d');

//if no end date set, make it one month in the future
$endDate = (array_key_exists('endDate', $_GET)) ? $_GET['endDate'] : date('Y-m-d', strtotime("+3 MONTH"));

do_action('elasticine_shows', $startDate, $endDate);
$shows = json_decode(get_transient('es_' . $startDate . "_" . $endDate));

/** END DATA GATHERING CODE **/

var_dump($shows);
```

## Installation

Copy the elasticine-artists.php into wp-content/plugins, and activate through Wordpress as with any other plugin. In the settings page, enter your company name as it appears in the "name" field of your company settings in Elasticine. 

## Contributors

Please email henry@elasticine.net if you find any bugs or have any feature requests. 

## License

GNU AGPLv3
