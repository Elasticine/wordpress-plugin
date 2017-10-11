<?php
/**
 * Plugin Name: Elasticine 
 * Description: Pulls in data from the Elasticine platform.
 * Version: 0.0.4
 * Author: Henry Franks
 *
 * 
    Elasticine wordpress plugin
    Copyright (C) 2016 Elasticine Ltd. 

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published
    by the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

 */
require_once(ABSPATH . 'wp-admin/includes/taxonomy.php'); 

/********************************************
 * Installation hooks and definitions
 ********************************************/
register_activation_hook(__FILE__, 'elasticine_install');
register_deactivation_hook(__FILE__, 'elasticine_uninstall');

function elasticine_install()
{
		//Create option database fields
		
		//What is the company name using the wordpress plugin?
		add_option('elasticine_artists_company_name', 'Elastic Artists Ltd.', '', 'yes');
		
		//Do we want to maintain a post category for each artist?
		add_option('elasticine_artists_maintain_categories', 'yes', '', 'no');
		
		//Where do we want to get the data from?
		add_option('elasticine_artists_base_url', 'system.elasticine.net/api', '', 'yes');

		//Do we want to filter by territory? 
		add_option('elasticine_artists_territory', '', '', 'yes');
}

function elasticine_uninstall()
{
		delete_option('elasticine_artists_company_name');
		delete_option('elasticine_artists_maintain_categories');
		delete_option('elasticine_artists_base_url');
		delete_option('elasticine_artists_territory');
}

/*******************************************
	Register the admin menu with wordpress
********************************************/
add_action( 'admin_menu', 'elasticine_artists_menu' );

function elasticine_artists_menu() {
	add_options_page( 'Elasticine options', 'Elasticine', 'manage_options', 'elasticine-options', 'elasticine_options' );
	add_options_page( 'Elasticine admin', 'Elasticine admin', 'manage_options', 'elasticine-admin', 'elasticine_admin' );
	register_setting('elasticine_settings', 'elasticine_artists_base_url');
	register_setting('elasticine_settings', 'elasticine_artists_company_name');
	register_setting('elasticine_settings', 'elasticine_artists_maintain_categories');
	register_setting('elasticine_settings', 'elasticine_artists_territory');
}

function elasticine_options() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
?>
	<div>
		<h2>Elasticine Options</h2>
		
		<form method="post" action="options.php">
		<?php wp_nonce_field('update-options');
				settings_fields( 'elasticine_settings' );
		?>
		
		<table width="100%">
				<tr valign="top">
						<th scope="row">Your company name</th>
								<td>
										<input name="elasticine_artists_company_name" type="text" id="elasticine_artists_company_name""
														value="<?php echo get_option('elasticine_artists_company_name'); ?>" />
												This should be the exact company name you have set in the Elasticine Agency Management System.
								</td>
				</tr>
		</table>
		<hr/>
		<table width="100%">
				<tr valign="top">
						<th scope="row">Territory for website</th>
								<td>
										<input name="elasticine_artists_territory" type="text" id="elasticine_artists_territory""
														value="<?php echo get_option('elasticine_artists_territory'); ?>" />
												If applicable, enter the territory name this website is for. If you do not use territories, or want all territories displayed, leave blank. 
								</td>
				</tr>
		</table>
		<hr/>
		<table width="100%">
				<tr valign="top">
						<th scope="row">Elasticine API URL</th>
								<td>
										<input name="elasticine_artists_base_url" type="text" id="elasticine_artists_base_url""
														value="<?php echo get_option('elasticine_artists_base_url'); ?>" />
												Where to get your company data from. Do not change this unless absolutely necessary. 
								</td>
				</tr>
		</table>
		<hr/>
		<table width="100%">
				<tr valign="top">
						<th scope="row">
								<label>
										<input name="elasticine_artists_maintain_categories" type="checkbox" id="elasticine_artists_maintain_categories"
														value="yes" <?php if (get_option('elasticine_artists_maintain_categories') == "yes") { echo "checked='checked'"; }?>/>
														Maintain artist post categories?
								</label>
						</th>
								<td>
												If you select this option, we will create a post category for every artist in your company, so you can see individual feeds of posts for each artist.
								</td>
				</tr>
		</table>
		<input type="hidden" name="action" value="update" />
		
		<p>
			<input type="submit" value="<?php _e('Save Changes') ?>" />
		</p>
		
		</form>
		</div>
<?php
}

function elasticine_admin() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}

	if (isset($_POST['forceUpdate']) && check_admin_referer('elasticine-admin')) 
	{
		global $wpdb; 
		$count = $wpdb->get_var( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_elasticine_%' OR option_name LIKE '_transient_es_%'" );
		echo "<div>Force update complete</div><br/>";
  	}
?>
	<div>
		<h2>Elasticine Admin</h2>
		
		<form method="post" action="options-general.php?page=elasticine-admin">
			<?php wp_nonce_field('elasticine-admin');
			?>

			<input type="hidden" value="true" name="forceUpdate" />
			<p>
				<input type="submit" value="<?php _e('Force update') ?>" />
			</p>
			
		</form>
		</div>
<?php
}

/*****************************************************
	Functions for getting data from elasticine API
******************************************************/

/**
 * First, define some constants (due to wordpress architectural considerations we can't just do this as a variable at this scope level).
 *
 * Also, for true/false constants, use 1/0 so we can check CONST==1 directly, as if(CONST) will return true if CONST is undefined. 
 */
define('ELASTICINE_DEBUG', 0); //1 for on, 0 for off
define('ELASTICINE_CACHE_PERIOD', 60*60*6);


/**
 * Returns the base URL for the elasticine API
 */
function elasticine_build_url()
{
	elastilog("elasticine_build_url");
	return 	"http://" . get_option('elasticine_artists_base_url') . "/json/" . sanitize_title(get_option('elasticine_artists_company_name'));
}

/**
 * Given an array of entities (shows or artists), we filter them, if necessary, by territory name.
 * By some magic coincidence, the JSON output from elasticine uses the same structure for territories for both shows and artists. 
 *
 * So when filtering by territory, we need to filter out:
 * 1) If there's an array of $agents on the top level, filter out any that don't have the territory of this site. (e.g. for artist objects)
 * 2) If the passed data is an array (e.g. of shows or of artists), filter to just those with the right territory. 
 * 3) If the passed data is an array, filter out any agent arrays of each object remaining after the previous step. 
 *
 * Also, we'll filter agents if necessary. 
 */
function elasticine_filter_territories($data)
{
	$websiteTerritory = strtolower(trim(get_option('elasticine_artists_territory')));
	elastilog("elasticine_filter_territories to " . $websiteTerritory);

	if (trim($websiteTerritory) == "")
	{
		elastilog("elasticine_filter_territories no territories set for website, returning");
		return $data; 
	} else {
		$dataToReturn = [];

		//Filter at top level for artist single page (step 1)
		$dataToReturn = elasticine_filter_agents($data, $websiteTerritory);

		if (is_array($dataToReturn))
		{
			//Step 2
			$dataToReturn = array_filter($dataToReturn, function($item) use ($websiteTerritory) {

				$territories = array_map(function($t) { return strtolower(trim($t)); }, $item->territories);
				$include = in_array($websiteTerritory, $territories); 
				return $include;

			});

			//Step 3
			$dataToReturn = array_map(function($dataItem) use ($websiteTerritory) { return elasticine_filter_agents($dataItem, $websiteTerritory); }, $dataToReturn);
		} else {
			$dataToReturn = $data;
		}

		return $dataToReturn; 
	}
}

function elasticine_filter_agents($item, $websiteTerritory)
{
	elastilog("elasticine_filter_agents " . count($item->agents));

	if (isset($item->agents) && count($item->agents) > 0)
	{
		$item->agents =  array_filter($item->agents, function($agent) use ($websiteTerritory) {

				$territories = array_map(function($t) { return strtolower(trim($t)); }, $agent->territories);
				$include = in_array($websiteTerritory, $territories); 
				return $include;

			});
	}

	return $item;
}

function elasticine_update_artists()
{
	$url = elasticine_build_url() . "/artists/";

	elastilog("elasticine_update_artists " . $url);

	$response = wp_remote_get($url, ['timeout' => 99999]);
	$http_code = wp_remote_retrieve_response_code( $response );

	if ($http_code == 200)
	{
		$body = wp_remote_retrieve_body($response);

		$body = json_encode(elasticine_filter_territories(json_decode($body)));

		set_transient('elasticine_artists', $body, ELASTICINE_CACHE_PERIOD);
	} else {
		elastilog("HTTP error " . $http_code);
		die("HTTP Error " . $http_code);
	}
}


/**
 *  Gets top-level info for all artists
 */
function elasticine_get_artists()
{		
	elastilog("elasticine_get_artists");

	//Get it from the cache
	$artists = get_transient('elasticine_artists');

	//Not in the cache? Get it from the API
	if ( $artists === false )
	{
		elasticine_update_artists();
		$artists = get_transient('elasticine_artists');
	}

	$artists = json_decode($artists);

	//Ensure the categories are up to date with the new info
	elasticine_maintainCategories($artists);

	return $artists;
}

/**
 * Gets detailed information for a single artist
 */
function elasticine_get_artist($slug)
{
    $transient = 'elasticine_artist_' . $slug;
	
	//If it's in the cache, don't worry
	$artist  = get_transient($transient);
	if ($artist !== false) { return json_decode($artist); }
	
	//It's not in the cache, so grab a copy of the data from the API
	$url = elasticine_build_url() . '/artist/' . $slug;
	$response = wp_remote_get($url);
	$http_code = wp_remote_retrieve_response_code( $response );
	if ($http_code == 200)
	{
		$body = wp_remote_retrieve_body($response);
		$artist = json_encode(elasticine_filter_territories(json_decode($body)));
		set_transient($transient, $artist, ELASTICINE_CACHE_PERIOD);
	} else {
		elastilog("HTTP error " . $http_code);
		die("HTTP Error");
	}
		
    //If called from do_action this return statement will be meaningless, but include it as we don't always
	//want to call this function from do_action
	return json_decode($artist);
}

/**
 * Gets all shows for a company between the given dates in yyyy-mm-dd format
 */
function elasticine_get_shows($startDate, $endDate)
{
	elastilog("elasticine_get_shows " . $startDate . " -- " . $endDate);

	$transient = "es_" . $startDate . "_" . $endDate;
	$shows = get_transient($transient);

	if ($shows === false) { return false; }

	$url = elasticine_build_url() . '/shows/' . $startDate . "/" . $endDate;
		
	$response = wp_remote_get($url);
	$http_code = wp_remote_retrieve_response_code( $response );
	if ($http_code == 200)
	{
		$body = wp_remote_retrieve_body($response);
		$body = json_encode(elasticine_filter_territories(json_decode($body)));
		set_transient($transient, $body, ELASTICINE_CACHE_PERIOD);
	} else {
		elastilog("HTTP error " . $http_code);
		die("HTTP Error " . $http_code);
	}
}

/**
 * Gets all shows for an artist between the given dates in yyyy-mm-dd format
 */
function elasticine_get_shows_artist($artist, $startDate, $endDate)
{
	elastilog("elasticine_get_shows_artist " . $artist . " -- " . $startDate . " -- " . $endDate);

	$transient = 'es_' . $artist . "_" . $startDate . "_" . $endDate;
	$shows = get_transient($transient);
	if ($shows !== false) { return; }
	
	$url = elasticine_build_url() . '/artist_shows/' . $startDate . "/" . $endDate . "/" . $artist;


	$response = wp_remote_get($url);
	$http_code = wp_remote_retrieve_response_code( $response );
	if ($http_code == 200)
	{
		$body = wp_remote_retrieve_body($response);
		$body = json_encode(elasticine_filter_territories(json_decode($body)));
		set_transient($transient, $body, ELASTICINE_CACHE_PERIOD);
	} else {
		elastilog("HTTP error " . $http_code);
		die("HTTP Error " . $http_code);
	}
}

/*****************************************************
	Register tags that can be used in themes
******************************************************/

add_action('elasticine_artist', 'elasticine_get_artist');
add_action('elasticine_artists', 'elasticine_get_artists');
add_action('elasticine_shows', 'elasticine_get_shows', 10, 2);
add_action('elasticine_shows_artist', 'elasticine_get_shows_artist', 10, 3);


/*****************************************************
 * Functions for maintaining the set of artist post categories
 *************************************************/

 function elasticine_maintainCategories($artists)
 {
		//All artist categories are a child of an "Artist" category. Make sure we have that!
		$parentID = wp_create_category('Artists');
		
		if (!elasticine_isCategoryUpdateNeeded($artists, $parentID)) { return; }
		
		$artistCategories = get_categories(array('child_of' => $parentID, 'hide_empty' => 0));
		
		//Create an O(1) data structure for artist names from the API, so that we can check which
		//don't exist without inducing an O(n^2) search. Arrays in PHP are hashtables so key lookups are O(1)
		//If a category exists which doesn't exist  in this array, then we need to delete that category as that artist
		//is no longer represented by the company. 
		$artistNames = array();
		
		//The create_category function nicely doesn't duplicate a category if it already exists,
		//so we can just create a category for each artist
		foreach($artists as $artist)
		{
				$newCatID = wp_create_category($artist->name, $parentID);
				
				//Populate the hashtable
				$artistNames[$artist->name] = 1;
		}
		
		//Now go through each category and delete any that don't exist from the API
		foreach($artistCategories as $category)
		{
				if (!array_key_exists($category->name, $artistNames))
				{
						wp_delete_category($category->cat_ID);
				}
		}
 }
 
 
 /**
  * This function hashes the API-artists and the stored categories and determines
  * if an update is needed
  */
function elasticine_isCategoryUpdateNeeded($artists, $artistCategoryID)
{
	$APINames = array();
	$categoryNames = array();
	
	//Build up arrays of just the names of each artist/category for comparison
	//Both input arrays are sorted by artist name ascending
	foreach($artists as $a)
	{
		$APINames[] = $a->name;
	}
	
	$artistCategories = get_categories(array('parent' => $artistCategoryID, 'hide_empty' => 0));
	foreach($artistCategories as $a)
	{
		$categoryNames[] = $a->name;
	}
	
	//Right, now check the hashes to see if they're different
	if (md5(serialize($APINames)) != md5(serialize($categoryNames)))
	{
		return true;
	} else {
		return false;
	}
}

/***************************************************************
 * The following functions are for creating re-write rules for permalinks
 *********************************************************************/

add_action( 'wp_loaded','elasticine_flush_rules' );
add_filter( 'rewrite_rules_array','elasticine_insert_rewrite_rules' );
add_filter( 'query_vars','elasticine_insert_query_vars' );
 
function elasticine_flush_rules() {
		$rules = get_option( 'rewrite_rules' );
		 
		if ( ! isset( $rules['(artist)/(.+)$'] ) ) {
				global $wp_rewrite;
				$wp_rewrite->flush_rules();
		}
		
		if ( ! isset( $rules['(shows)/(.+)$'] ) ) {
				global $wp_rewrite;
				$wp_rewrite->flush_rules();
		}
		
		if ( ! isset( $rules['(agent)/(.+)$'] ) ) {
				global $wp_rewrite;
				$wp_rewrite->flush_rules();
		}
}

function elasticine_insert_rewrite_rules( $rules ) {
		$newrules = array();
		$newrules['(artist)/(.+)$'] = 'index.php?pagename=$matches[1]&artist_slug=$matches[2]';
		$newrules['(shows)/(.+)$'] = 'index.php?pagename=$matches[1]&artist_slug=$matches[2]';
		$newrules['(agent)/(.+)$'] = 'index.php?pagename=$matches[1]&agent_slug=$matches[2]';
		return $newrules + $rules;
}

function elasticine_insert_query_vars( $vars ) {
 
		array_push($vars, 'artist_slug');
		array_push($vars, 'agent_slug');
		return $vars;
}


function elastilog($str)
{
	if (ELASTICINE_DEBUG == 1)
	{
		$logFile = getcwd() . "/elasticine_wordpress.log";

		$handle = fopen($logFile, 'a+') or die('Cannot open elasticine log file:  '.$logFile);
		fwrite($handle, "[" . date('Y-m-d H:i:s') . "] " . $str . "\n");
		fclose($handle);
	}
}
