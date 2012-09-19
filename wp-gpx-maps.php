<?php
/*
Plugin Name: WP-GPX-Maps
Plugin URI: http://www.darwinner.it/
Description: Draws a gpx track with altitude graph
Version: 1.1.37
Author: Bastianon Massimo
Author URI: http://www.pedemontanadelgrappa.it/
*/

//error_reporting (E_ALL);

load_plugin_textdomain('wp-gpx-maps', "/wp-content/plugins/wp-gpx-maps/languages/");

include 'wp-gpx-maps_utils.php';
include 'wp-gpx-maps_admin.php';

add_action( 'wp_print_scripts', 'print_WP_GPX_Maps_scripts' );
add_shortcode('sgpx','handle_WP_GPX_Maps_Shortcodes');
register_activation_hook(__FILE__,'WP_GPX_Maps_install'); 
register_deactivation_hook( __FILE__, 'WP_GPX_Maps_remove');	
add_filter('plugin_action_links', 'WP_GPX_Maps_action_links', 10, 2);
add_action('wp_enqueue_scripts', 'enqueue_WP_GPX_Maps_scripts');

function WP_GPX_Maps_action_links($links, $file) {
    static $this_plugin;
 
    if (!$this_plugin) {
        $this_plugin = plugin_basename(__FILE__);
    }
 
    // check to make sure we are on the correct plugin
    if ($file == $this_plugin) {
        // the anchor tag and href to the URL we want. For a "Settings" link, this needs to be the url of your settings page
        $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/options-general.php?page=WP-GPX-Maps">Settings</a>';
        // add the link to the list
        array_unshift($links, $settings_link);
    }
 
    return $links;
}

function enqueue_WP_GPX_Maps_scripts()
{

	//wp_enqueue_script( 'jquery' );
	
	wp_deregister_script( 'googleapis' );
    wp_register_script( 'googleapis', 'https://maps.googleapis.com/maps/api/js?sensor=false&v=3.9', null, null);
    wp_enqueue_script( 'googleapis' );
	
    wp_deregister_script( 'WP-GPX-Maps' );
    wp_register_script( 'WP-GPX-Maps', plugins_url('/WP-GPX-Maps.js', __FILE__), array('jquery'), "1.1.34");
    wp_enqueue_script( 'WP-GPX-Maps' );
	
    wp_deregister_script( 'highcharts' );
    wp_register_script( 'highcharts', "http://code.highcharts.com/highcharts.js", array('jquery'), "2.3.2", true);
    wp_enqueue_script( 'highcharts' );
		
}

function print_WP_GPX_Maps_scripts()
{
?>
<style type="text/css">
	.wpgpxmaps { clear:both; }
	#content .wpgpxmaps img,
	.entry-content .wpgpxmaps img,
	.wpgpxmaps img { max-width: none; width: none; padding:0; background:none; margin:0; border:none; }
	.wpgpxmaps .ngimages { display:none; }
	.wpgpxmaps .myngimages { border:1px solid #fff;position:absolute;cursor:pointer;margin:0;z-index:1; }
	.wpgpxmaps_summary .summarylabel { }
	.wpgpxmaps_summary .summaryvalue { font-weight: bold; }
</style>
<?php
}

function findValue($attr, $attributeName, $optionName, $defaultValue)
{
	$val = '';
	if ( isset($attr[$attributeName]) )
	{
		$val = $attr[$attributeName];
	}
	if ($val == '')
	{
		$val = get_option($optionName);
	}
	if ($val == '' && isset($_GET[$attributeName]) && $attributeName != "download")
	{
		$val = $_GET[$attributeName];
	}
	if ($val == '')
	{
		$val = $defaultValue;
	}
	return $val;
}

function handle_WP_GPX_Maps_Shortcodes($attr, $content='')
{

	$error = '';

	$gpx =                findValue($attr, "gpx",                "",                          		 "");
	$w =                  findValue($attr, "width",              "wpgpxmaps_width",           		 "100%");
	$mh =                 findValue($attr, "mheight",            "wpgpxmaps_height",          		 "450px");
	$mt =                 findValue($attr, "mtype",              "wpgpxmaps_map_type",        		 "HYBRID");
	$gh =                 findValue($attr, "gheight",            "wpgpxmaps_graph_height",    		 "200px");
	$showCad =            findValue($attr, "showcad",            "wpgpxmaps_show_cadence",   		 false);
	$showHr =             findValue($attr, "showhr",             "wpgpxmaps_show_hr",   		 	 false);
	$showW =              findValue($attr, "waypoints",          "wpgpxmaps_show_waypoint",   		 false);
	$showSpeed =          findValue($attr, "showspeed",          "wpgpxmaps_show_speed",      		 false);
	$zoomOnScrollWheel =  findValue($attr, "zoomonscrollwheel",  "wpgpxmaps_zoomonscrollwheel",      false);
	$donotreducegpx =     findValue($attr, "donotreducegpx",     "wpgpxmaps_donotreducegpx", 		 false);
	$pointsoffset =       findValue($attr, "pointsoffset",       "wpgpxmaps_pointsoffset",     		 10);
	$uom =                findValue($attr, "uom",                "wpgpxmaps_unit_of_measure",        "0");
	$uomspeed =           findValue($attr, "uomspeed",           "wpgpxmaps_unit_of_measure_speed",  "0");
	$color_map =          findValue($attr, "mlinecolor",         "wpgpxmaps_map_line_color",         "#3366cc");
	$color_graph =        findValue($attr, "glinecolor",         "wpgpxmaps_graph_line_color",       "#3366cc");
	$color_graph_speed =  findValue($attr, "glinecolorspeed",    "wpgpxmaps_graph_line_color_speed", "#ff0000");
	$color_graph_hr =  	  findValue($attr, "glinecolorhr",       "wpgpxmaps_graph_line_color_hr",    "#ff77bd");
	$color_graph_cad =    findValue($attr, "glinecolorcad",      "wpgpxmaps_graph_line_color_cad",   "#beecff");
	$chartFrom1 =         findValue($attr, "chartfrom1",         "wpgpxmaps_graph_offset_from1",     "");
	$chartTo1 =           findValue($attr, "chartto1",           "wpgpxmaps_graph_offset_to1",       "");
	$chartFrom2 =         findValue($attr, "chartfrom2",         "wpgpxmaps_graph_offset_from2", 	 "");
	$chartTo2 =           findValue($attr, "chartto2",           "wpgpxmaps_graph_offset_to2", 		 "");
	$startIcon =          findValue($attr, "starticon",          "wpgpxmaps_map_start_icon", 		 "");
	$endIcon =            findValue($attr, "endicon",            "wpgpxmaps_map_end_icon", 			 "");
	$currentIcon =        findValue($attr, "currenticon",        "wpgpxmaps_map_current_icon", 		 "");
	$waypointIcon =       findValue($attr, "waypointicon",       "wpgpxmaps_map_waypoint_icon", 	 "");
	$ngGalleries =        findValue($attr, "nggalleries",        "wpgpxmaps_map_ngGalleries", 		 "");
	$ngImages =           findValue($attr, "ngimages",           "wpgpxmaps_map_ngImages", 		     "");
	$download =           findValue($attr, "download",           "wpgpxmaps_download", 		     	 "");
	$dtoffset =           findValue($attr, "dtoffset",           "wpgpxmaps_dtoffset", 		     	 0);
	$skipcache =          findValue($attr, "skipcache",          "wpgpxmaps_skipcache", 	     	 "");
	
	$summary =            findValue($attr, "summary",            "wpgpxmaps_summary", 		     	 "");	
	$p_tot_len =          findValue($attr, "summarytotlen",      "wpgpxmaps_summary_tot_len",      	 false);
	$p_max_ele =          findValue($attr, "summarymaxele",      "wpgpxmaps_summary_max_ele",      	 false);
	$p_min_ele =          findValue($attr, "summaryminele",      "wpgpxmaps_summary_min_ele",      	 false);
	$p_total_ele_up =     findValue($attr, "summaryeleup",       "wpgpxmaps_summary_total_ele_up",   false);
	$p_total_ele_down =   findValue($attr, "summaryeledown",     "wpgpxmaps_summary_total_ele_down", false);
	$p_avg_speed =        findValue($attr, "summaryavgspeed",    "wpgpxmaps_summary_avg_speed",      false);
	$p_total_time =       findValue($attr, "summarytotaltime",   "wpgpxmaps_summary_total_time",     false);
	
	$colors_map = "\"".implode("\",\"",(explode(" ",$color_map)))."\"";

	$gpxurl = $gpx;
	
	$cacheFileName = "$gpx,$w,$mh,$mt,$gh,$showW,$showHr,$showCad,$donotreducegpx,$pointsoffset,$showSpeed,$uomspeed,$uom,v1.1.32";

	$cacheFileName = md5($cacheFileName);
	
	$gpxcache = gpxCacheFolderPath();
	
	if(!(file_exists($gpxcache) && is_dir($gpxcache)))
		@mkdir($gpxcache,0755,true);
	
	$gpxcache.= DIRECTORY_SEPARATOR.$cacheFileName.".tmp";
	
	// Try to load cache
	if (file_exists($gpxcache) && !($skipcache == true))
	{
	
		try {
			$cache_str = file_get_contents($gpxcache);
			$cache_obj = unserialize($cache_str);
			$points_maps = $cache_obj["points_maps"];
			$points_x_time = $cache_obj["points_x_time"];
			$points_x_lat = $cache_obj["points_x_lat"];
			$points_x_lon = $cache_obj["points_x_lon"];
			$points_graph_dist = $cache_obj["points_graph_dist"];
			$points_graph_ele = $cache_obj["points_graph_ele"];
			$points_graph_speed = $cache_obj["points_graph_speed"];
			$points_graph_hr = $cache_obj["points_graph_hr"];
			$points_graph_cad = $cache_obj["points_graph_cad"];
			$waypoints = $cache_obj["waypoints"];
			$max_ele = $cache_obj["max_ele"];
			$min_ele = $cache_obj["min_ele"];
			$max_time = $cache_obj["max_time"];
			$min_time = $cache_obj["min_time"];
			$total_ele_up = $cache_obj["total_ele_up"];
			$total_ele_down = $cache_obj["total_ele_down"];
			$avg_speed = $cache_obj["avg_speed"];
			$tot_len = $cache_obj["tot_len"];
			
		} catch (Exception $e) {
			$points_maps = '';
			$points_x_time = '';
			$points_x_lat = '';
			$points_x_lon = '';
			$points_graph_dist = '';
			$points_graph_ele = '';
			$points_graph_speed = '';
			$points_graph_hr = '';
			$points_graph_cad = '';
			$waypoints= '';
			$max_ele = 0;
			$min_ele = 0;
			$max_time = 0;
			$min_time = 0;
			$total_ele_up = 0;
			$total_ele_down = 0;
			$avg_speed = 0;
			$tot_len = 0;
		}
	}

	if ($points_maps == '' && $gpx != '')
	{
	
		$sitePath = sitePath();
		
		$gpx = trim($gpx);
		
		$isGpxUrl = (preg_match('/^(http(s)?\:\/\/)/', $gpx) == 1);
		
		if ($isGpxUrl == true)
		{
			$gpx = downloadRemoteFile($gpx);
		}
		else
		{
			$gpx = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $gpx);
			$gpx = $sitePath . $gpx;
		}

		if ($gpx == '')
		{
			return "No gpx found";
		}
		
		$points = getPoints( $gpx, $pointsoffset, $donotreducegpx);
		
		$points_maps = '';
		$points_graph_dist = '';
		$points_graph_ele = '';
		$points_graph_speed = '';
		$points_graph_hr = '';
		$points_graph_cad = '';
		$waypoints = '';

		$points_x_time = $points->dt;
		$points_x_lat = $points->lat;
		$points_x_lon = $points->lon;
		
		$max_ele = $points->maxEle;
		$min_ele = $points->minEle;
		$max_time = $points->maxTime;
		$min_time =  $points->minTime;
		$total_ele_up = $points->totalEleUp;
		$total_ele_down = $points->totalEleDown;
		$avg_speed = $points->avgSpeed;
		$tot_len = $points->totalLength;
			
		if (is_array ($points_x_lat))
		foreach(array_keys($points_x_lat) as $i) 
		{
			
			$_lat = (float)$points_x_lat[$i];
			$_lon = (float)$points_x_lon[$i];
			
			if ( $_lat == 0 && $_lon == 0 )
			{
				$points_maps .= 'null,';
				$points_graph_dist .= 'null,';
				$points_graph_ele .= 'null,';
					
				if ($showSpeed == true) 
					$points_graph_speed .= 'null,';

				if ($showHr == true)
					$points_graph_hr .= 'null,';
					
				if ($showCad == true)
					$points_graph_cad .= 'null,';
			}
			else
			{
				$points_maps .= '['.(float)$points_x_lat[$i].','.(float)$points_x_lon[$i].'],';	

				$_ele = (float)$points->ele[$i];	
				$_dist = (float)$points->dist[$i];	
				
				if ($uom == '1')
				{
					// Miles and feet			
					$_dist *= 0.000621371192;
					$_ele *= 3.2808399;
				} else if ($uom == '2')
				{
					// meters / kilometers
					$_dist = (float)($_dist / 1000);
				}
				
				$points_graph_dist .= $_dist.',';
				$points_graph_ele .= $_ele.',';
					
				if ($showSpeed == true) {
				
					$_speed = (float)$points->speed[$i];
					
					$points_graph_speed .= convertSpeed($_speed,$uomspeed).',';
				}
				
				if ($showHr == true)
				{
					$points_graph_hr .= $points->hr[$i].',';
				}
				
				if ($showCad == true)
				{
					$points_graph_cad .= $points->cad[$i].',';
				}
			}
			
		}	
		
		if ($uom == '1')
		{
			// Miles and feet			
			$tot_len = round($tot_len * 0.000621371192, 2)." mi";
			$max_ele = round($max_ele * 3.2808399, 0)." ft";
			$min_ele = round($min_ele * 3.2808399, 0)." ft";
			$total_ele_up = round($total_ele_up * 3.2808399, 0)." ft";
			$total_ele_down = round($total_ele_down * 3.2808399, 0)." ft";			
		} else if ($uom == '2')
		{
			// meters / kilometers
			$tot_len = round($tot_len / 1000, 2)." km";
			$max_ele = round($max_ele, 0) ." m";
			$min_ele = round($min_ele, 0) ." m";
			$total_ele_up = round($total_ele_up, 0) ." m";
			$total_ele_down = round($total_ele_down, 0) ." m";
		}
		else
		{
			// meters / meters
			$tot_len = round($tot_len, 0) ." m";
			$max_ele = round($max_ele, 0) ." m";
			$min_ele = round($min_ele, 0) ." m";
			$total_ele_up = round($total_ele_up, 0) ." m";
			$total_ele_down = round($total_ele_down, 0) ." m";
		}
		
		$avg_speed = convertSpeed($avg_speed,$uomspeed,true);
			
		if ($showW == true)
		{
			$wpoints = getWayPoints($gpx);
			foreach ($wpoints as $p) {
				$waypoints .= '['.(float)$p[0].','.(float)$p[1].',\''.unescape($p[4]).'\',\''.unescape($p[5]).'\',\''.unescape($p[7]).'\'],';
			}
		}

		$p="/(,|,null,)$/";

		$points_maps = preg_replace($p, "", $points_maps);

		$points_graph_dist = preg_replace($p, "", $points_graph_dist);
		$points_graph_ele = preg_replace($p, "", $points_graph_ele);
		$points_graph_speed = preg_replace($p, "", $points_graph_speed);
		$points_graph_hr = preg_replace($p, "", $points_graph_hr);
		$points_graph_cad = preg_replace($p, "", $points_graph_cad);
			
		$waypoints = preg_replace($p, "", $waypoints);
		
		if (preg_match("/^(0,?)+$/", $points_graph_dist)) 
			$points_graph_dist = "";
			
		if (preg_match("/^(0,?)+$/", $points_graph_ele)) 
			$points_graph_ele = "";
			
		if (preg_match("/^(0,?)+$/", $points_graph_speed)) 
			$points_graph_speed = "";
			
		if (preg_match("/^(0,?)+$/", $points_graph_hr)) 
			$points_graph_hr = "";
			
		if (preg_match("/^(0,?)+$/", $points_graph_cad)) 
			$points_graph_cad = "";
		
	}
	
	$ngimgs_data = '';
	if ( $ngGalleries != '' || $ngImages != '' )
	{
	
	//print_r($points);
	
		$ngimgs = getNGGalleryImages($ngGalleries, $ngImages, $points_x_time, $points_x_lat, $points_x_lon, $dtoffset, $error);
		$ngimgs_data ='';	
		foreach ($ngimgs as $img) {		
			$data = $img['data'];
			$data = str_replace("\n","",$data);
			$ngimgs_data .= '<span lat="'.$img['lat'].'" lon="'.$img['lon'].'">'.$data.'</span>';
		}
	}
	
	if (!($skipcache == true))
	{
		@file_put_contents($gpxcache, 
					   serialize(array( "points_maps"         => $points_maps, 
										"points_x_time"       => $points_x_time,
										"points_x_lat"        => $points_x_lat,
										"points_x_lon"        => $points_x_lon,
										"points_graph_dist"   => $points_graph_dist, 
										"points_graph_ele"    => $points_graph_ele, 
										"points_graph_speed"  => $points_graph_speed, 
										"points_graph_hr"     => $points_graph_hr, 
										"points_graph_cad"    => $points_graph_cad,
										"waypoints"           => $waypoints,
										"max_ele"             => $max_ele,
										"min_ele"             => $min_ele,
										"total_ele_up"        => $total_ele_up,
										"total_ele_down"      => $total_ele_down,
										"avg_speed"           => $avg_speed,
										"tot_len"             => $tot_len,
										"max_time"			  => $max_time,
										"min_time"			  => $min_time
										)
								), 
					   LOCK_EX);
		@chmod($gpxcache,0755);		
	}
	
	$hideGraph = ($gh == "0" || $gh == "0px");
	
	global $post;
	$r = $post->ID."_".rand(1,5000000);	
	
	$output = '
		<div id="wpgpxmaps_'.$r.'" class="wpgpxmaps">
			<div id="map_'.$r.'" style="width:'.$w.'; height:'.$mh.'"></div>
			<div id="hchart_'.$r.'" class="plot" style="width:'.$w.'; height:'.$gh.'"></div>
			<div id="ngimages_'.$r.'" class="ngimages" style="display:none">'.$ngimgs_data.'</div>
		</div>
		'. $error .'
		<script type="text/javascript">
		    jQuery(document).ready(function() {
				wpgpxmaps({ targetId    : "'.$r.'",
							mapType     : "'.$mt.'",
							mapData     : ['.$points_maps.'],
							graphDist   : ['.($hideGraph ? '' : $points_graph_dist).'],
							graphEle    : ['.($hideGraph ? '' : $points_graph_ele).'],
							graphSpeed  : ['.($hideGraph ? '' : $points_graph_speed).'],
							graphHr     : ['.($hideGraph ? '' : $points_graph_hr).'],
							graphCad    : ['.($hideGraph ? '' : $points_graph_cad).'],
							waypoints   : ['.$waypoints.'],
							unit        : "'.$uom.'",
							unitspeed   : "'.$uomspeed.'",
							color1      : ['.$colors_map.'],
							color2      : "'.$color_graph.'",
							color3      : "'.$color_graph_speed.'",
							color4      : "'.$color_graph_hr.'",
							color5      : "'.$color_graph_cad.'",
							chartFrom1  : "'.$chartFrom1.'",
							chartTo1    : "'.$chartTo1.'",
							chartFrom2  : "'.$chartFrom2.'",
							chartTo2    : "'.$chartTo2.'",
							startIcon   : "'.$startIcon.'",
							endIcon     : "'.$endIcon.'",
							currentIcon : "'.$currentIcon.'",
							waypointIcon : "'.$waypointIcon.'",
							zoomOnScrollWheel : "'.$zoomOnScrollWheel.'", 
							pluginUrl : "'.plugins_url().'",
							langs : { altitude              : "'.__("Altitude", "wp-gpx-maps").'",
									  currentPosition       : "'.__("Current Position", "wp-gpx-maps").'",
									  speed                 : "'.__("Speed", "wp-gpx-maps").'", 
									  heartRate             : "'.__("Heart rate", "wp-gpx-maps").'", 
									  cadence               : "'.__("Cadence", "wp-gpx-maps").'",
									  gofullscreen          : "'.__("Go Full Screen", "wp-gpx-maps").'",
									  exitfullscreen        : "'.__("Exit Full Screen", "wp-gpx-maps").'"
									}
						   });
			});
		</script>';	

	// print summary
	if ($summary=='true' && ( $points_graph_speed != '' || $points_graph_ele != '' || $points_graph_dist != '') )
	{
	
		$output .= "<div id='wpgpxmaps_summary_".$r."' class='wpgpxmaps_summary'>";
		if ($points_graph_dist != '' && $p_tot_len == 'true')
		{
			$output .= "<span class='totlen'><span class='summarylabel'>".__("Total distance", "wp-gpx-maps").":</span><span class='summaryvalue'> $tot_len</span></span><br />";
		}
		if ($points_graph_ele != '')
		{
			if ($p_max_ele == 'true')
				$output .= "<span class='maxele'><span class='summarylabel'>".__("Max elevation", "wp-gpx-maps").":</span><span class='summaryvalue'> $max_ele</span></span><br />";
			if ($p_min_ele == 'true')
				$output .= "<span class='minele'><span class='summarylabel'>".__("Min elevation", "wp-gpx-maps").":</span><span class='summaryvalue'> $min_ele</span></span><br />";
			if ($p_total_ele_up == 'true')
				$output .= "<span class='totaleleup'><span class='summarylabel'>".__("Total climbing", "wp-gpx-maps").":</span><span class='summaryvalue'> $total_ele_up</span></span><br />";
			if ($p_total_ele_down == 'true')
				$output .= "<span class='totaleledown'><span class='summarylabel'>".__("Total descent", "wp-gpx-maps").":</span><span class='summaryvalue'> $total_ele_down</span></span><br />";
		}
		if ($points_graph_speed != '' && $p_avg_speed == 'true')
		{
			$output .= "<span class='avgspeed'><span class='summarylabel'>".__("Average speed", "wp-gpx-maps").":</span><span class='summaryvalue'> $avg_speed</span></span><br />";
		}
		if ($p_total_time == 'true' && $max_time > 0)
		{		
			$time_diff = date("H:i:s", ($max_time - $min_time));
			$output .= "<span class='totaltime'><span class='summarylabel'>".__("Total Time", "wp-gpx-maps").":</span><span class='summaryvalue'> $time_diff</span></span><br />";			
		}
		$output .= "</div>";
	}
	
	// print download link
	if ($download=='true' && $gpxurl != '')
	{
		if ($isGpxUrl == true)
		{

		}
		else
		{
			$gpxurl = get_bloginfo('url').$gpxurl;
		}		
		$output.="<a href='$gpxurl' target='_new'>".__("Download", "wp-gpx-maps")."</a>";
	}

	return $output;
}

function convertSeconds($s)
{
	if ($s ==0)
		return 0;
	$s =  1.0 / $s;
	$_sSecT = $s * 60; //sec/km
	$_sMin = floor ( $_sSecT / 60 );
	$_sSec = $_sSecT - $_sMin * 60;
	return $_sMin + $_sSec / 100;
}

function convertSpeed($speed,$uomspeed, $addUom = false)
{
	if ($uomspeed == '4') // min/mi
	{
		$speed = convertSeconds($speed * 0.037282272);	
		if ($addUom == true) $speed = round($speed,2) . " min/mi";
	} 
	else if ($uomspeed == '3') // min/km
	{
		$speed = convertSeconds($speed * 0.06);
		if ($addUom == true) $speed = round($speed,2) . " min/km";
	} 
	else if ($uomspeed == '2') // miles/h
	{
		$speed *= 2.2369362920544025;
		if ($addUom == true) $speed = round($speed,2) . " mi/h";
	} 
	else if ($uomspeed == '1') // km/h
	{
		$speed *= 3.6;
		if ($addUom == true) $speed = round($speed,2) . " km/h";
	}
	else	// dafault m/s
	{
		if ($addUom == true) $speed = round($speed,2) . " m/s";
	}
	return $speed;
}

function downloadRemoteFile($remoteFile)
{
	try
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, urlencode($remoteFile)); 
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,5);
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
		$resp = curl_exec($ch); 
		curl_close($ch);
		$tmpfname = tempnam ( '/tmp', 'gpx' );
		
		$fp = fopen($tmpfname, "w");
		fwrite($fp, $resp);
		fclose($fp);
		
		return $tmpfname;
	} catch (Exception $e) {
		return '';
	}
}

function unescape($value)
{
	$value = str_replace("'", "\'", $value);
	$value = str_replace(array("\n","\r"), "", $value);
	return $value;
}

function WP_GPX_Maps_install() {
	add_option("wpgpxmaps_width", '100%', '', 'yes');
	add_option("wpgpxmaps_graph_height", '200px', '', 'yes');
	add_option("wpgpxmaps_height", '450px', '', 'yes');
	add_option('wpgpxmaps_map_type','HYBRID','','yes');
	add_option('wpgpxmaps_show_waypoint','','','yes');
	add_option('wpgpxmaps_show_speed','','','yes');
	add_option('wpgpxmaps_pointsoffset','10','','yes');
	add_option('wpgpxmaps_donotreducegpx','true','','yes');
	add_option("wpgpxmaps_unit_of_measure", '0', '', 'yes');
	add_option("wpgpxmaps_unit_of_measure_speed", '0', '', 'yes');
	add_option("wpgpxmaps_graph_line_color", '#3366cc', '', 'yes');
	add_option("wpgpxmaps_graph_line_color_speed", '#ff0000', '', 'yes');
	add_option("wpgpxmaps_map_line_color", '#3366cc', '', 'yes');
	add_option("wpgpxmaps_graph_line_color_cad", '#beecff', '', 'yes');
	add_option("wpgpxmaps_graph_offset_from1", '', '', 'yes');
	add_option("wpgpxmaps_graph_offset_to1", '', '', 'yes');
	add_option("wpgpxmaps_graph_offset_from2", '', '', 'yes');
	add_option("wpgpxmaps_graph_offset_to2", '', '', 'yes');
	add_option("wpgpxmaps_map_start_icon", '', '', 'yes');
	add_option("wpgpxmaps_map_end_icon", '', '', 'yes');
	add_option("wpgpxmaps_map_current_icon", '', '', 'yes');
	add_option("wpgpxmaps_map_waypoint_icon", '', '', 'yes');
	add_option("wpgpxmaps_map_nggallery", '', '', 'yes');
	add_option("wpgpxmaps_show_hr", '', '', 'yes');
	add_option("wpgpxmaps_graph_line_color_hr", '#ff77bd', '', 'yes');
	add_option('wpgpxmaps_show_cadence','','','yes');
	add_option('wpgpxmaps_zoomonscrollwheel','','','yes');
	add_option('wpgpxmaps_download','','','yes');
	add_option('wpgpxmaps_summary','','','yes');	
	add_option('wpgpxmaps_skipcache','','','yes');
}

function WP_GPX_Maps_remove() {
	delete_option('wpgpxmaps_width');
	delete_option('wpgpxmaps_graph_height');
	delete_option('wpgpxmaps_height');
	delete_option('wpgpxmaps_map_type');
	delete_option('wpgpxmaps_show_waypoint');
	delete_option('wpgpxmaps_show_speed');
	delete_option('wpgpxmaps_pointsoffset');
	delete_option('wpgpxmaps_donotreducegpx');
	delete_option('wpgpxmaps_unit_of_measure');
	delete_option('wpgpxmaps_unit_of_measure_speed');
	delete_option('wpgpxmaps_graph_line_color');
	delete_option('wpgpxmaps_map_line_color');
	delete_option('wpgpxmaps_graph_line_color_speed');
	delete_option('wpgpxmaps_graph_offset_from1');
	delete_option('wpgpxmaps_graph_offset_to1');
	delete_option('wpgpxmaps_graph_offset_from2');
	delete_option('wpgpxmaps_graph_offset_to2');
	delete_option('wpgpxmaps_map_start_icon');
	delete_option('wpgpxmaps_map_end_icon');
	delete_option('wpgpxmaps_map_current_icon');
	delete_option('wpgpxmaps_map_waypoint_icon');
	delete_option('wpgpxmaps_map_nggallery');
	delete_option('wpgpxmaps_show_hr');
	delete_option('wpgpxmaps_graph_line_color_hr');
	delete_option('wpgpxmaps_show_cadence');
	delete_option('wpgpxmaps_graph_line_color_cad');
	delete_option('wpgpxmaps_zoomonscrollwheel');
	delete_option('wpgpxmaps_download');
	delete_option('wpgpxmaps_summary');
	delete_option('wpgpxmaps_skipcache');
}

?>
