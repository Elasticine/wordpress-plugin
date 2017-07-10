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

## API Reference

The Elasticine main system is located at system.elasticine.net. The JSON API is located at system.elasticine.net/api/json/{company-url-slug}/{endpoint} -- currently only JSON is supported. To get a URL slug for a company using the Elasticine platform please contact us. 

*Usage*: There are currently no rate limits but we do monitor access. We recommend including a caching layer on your website implementation since the information on this API will change relatively infrequently. We recommend re-querying not more frequently than every 6 hours. 

The Elasticine API has 4 endpoints: artists list, artist info, shows list, artist specific shows. 

### Artists list

system.elasticine.net/api/json/{company-url-slug}/artists/ 

Returns an array of artists, with a summary of important info, on a company's public roster. 

```javascript
[
	{
		"name":"Artist name",
		"profilePic":"URL to artist profile picture",
		"featuredAssets": //Array of featured artist assets 
			[
				{
					"title":"Asset title",
					"isImage":"1",
					"url":"URL to artist asset",
					"thumb":"If asset is image, URL to thumbnail resize of asset"
				}
			],
		"doesDJ":"1", //Artists on Elasticine can be listed as performing DJ sets, performing live, or both
		"doesLive":"0",
		"agents":	//Array of agents that represent the artist
			[
				{
					"name":"Agent name",
					"email":"Agent email",
					"territories": //Array of territory labels that the agent deals with, if company uses territory features (in which an artist may have one agent for one territory, and another for a different one
							["Worldwide", "Australia"]
				}
			],
		"slug":"artist-slug", //Artist slug to access artist specific API endpoints
		"territories":["Worldwide", "Australia"] //Territories that the artist plays in
	},
]
```

### Artist specific information

system.elasticine.net/api/json/{company-url-slug}/artist/{artist-url-slug} 

Returns a detailed JSON representation of an artist's public profile. 

```javascript
{
	"name":"Artist name",
	"slug":"artist-slug",
	"website":"URL to artist website",
	"profilePic":"URL to artist profile picture",
	"soundcloud":"URL to artist soundcloud",
	"facebook":"URL to artist facebook",
	"twitter":"URL to artist twitter",
	"instagram":"URL to artist instagram",
	"youtube":"URL to artist youtube",
	"agents": //Array of agents representing artist, same as artists/ endpoint but also including array of any assistants assigned to artist
		[
			{
				"name":"Agent name",
				"email":"Agent email",
				"assistants":[{"name":"Assistant name","email":"Assistant email"}],
				"territories":[]
			}
		],
	"tagline":"Artist tagline",
	"biography":"Artist biography with HTML formatting",
	"info":"Additional artist information with HTML formatting",
	"links":[],
	"featuredLinks":[],
	"pressAssets":[],
	"featuredAssets":[],
	"labels":[], //Array of strings, name of labels that artist has released on
	"territories":[] //Array of strings, name of territories that artist plays in
}
```

Platform users have the option of adding any number of links (to web properties) and assets (files, including images) to an artist's public profile. These can be marked featured or not, allowing the user to potentially control (for example) display logic on the agency website, highlighting certain links and assets. 

The format of a link in the links & featuredLinks arrays is:

```javascript
{
	"text":"Link text to display",
	"url":"Link URL",
	"type":"Link type -- Web, Soundcloud, Youtube, Facebook"
}
```

The format of an asset in the assets and featuredAssets arrays is:

```javascript
{
	"title":"Asset title",
	"isImage":"1", //Or 0 if not an image...
	"url":"URL to artist asset",
	"thumb":"If asset is image, URL to thumbnail resize of asset"
}
```

### Shows

system.elasticine.net/api/json/{company-url-slug}/shows/{startDate}/{endDate}

Returns an array of announced, confirmed shows for artists on the company roster between startDate and endDate, formatted as dd-mm-yyyy. 

```javascript
[
	{	
		"date":"01-Jan-17",
		"agents":[], //Array of agents, same format as in artists endpoint
		"announced":"23-Mar-16", //Date show was announced
		"artist":"Name of artist",
		"billedAs":"Show-specific billing name of artist",
		"event":"Event name for show",
		"facebookLink":"URL for show facebook page",
		"ticketLink":"URL for show ticket purchase page",
		"venue":"Name of venue",
		"city":"Show location city",
		"country":"Show location country as ISO code (e.g. 'GB')",
		"territories":[] //Same as artists & agents, a show can be for a specific territory or territories 
	}
```

### Artist specific shows

system.elasticine.net/api/json/{company-url-slug}/artist_shows/{startDate}/{endDate}/{artistSlug}

Returns an array of announced, confirmed shows for artists on the company roster between startDate and endDate, formatted as dd-mm-yyyy, for the artist specified
in artistSlug. 

Each show has the same format as in the shows/ endpoint. 

## Contributors

Please email henry@elasticine.net if you find any bugs or have any feature requests. 

## License

GNU AGPLv3
