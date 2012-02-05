<?php
/*
Plugin Name: WP-GPX-Maps
Plugin URI: http://www.darwinner.it/
Description: Draws a gpx track with altitude graph
Version: 1.1.4
Author: Bastianon Massimo
Author URI: http://www.pedemontanadelgrappa.it/
License: GPL
*/

//error_reporting (E_ALL);

include 'wp-gpx-maps_Utils.php';
include 'wp-gpx-maps_admin.php';

add_action( 'wp_print_scripts', 'enqueue_WP_GPX_Maps_scripts' );
add_shortcode('sgpx','handle_WP_GPX_Maps_Shortcodes');
register_activation_hook(__FILE__,'WP_GPX_Maps_install'); 
register_deactivation_hook( __FILE__, 'WP_GPX_Maps_remove');	
add_filter('plugin_action_links', 'WP_GPX_Maps_action_links', 10, 2);

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
?>
	<script type='text/javascript' src='https://www.google.com/jsapi?ver=3.2.1'></script>
	<script type='text/javascript'>
		google.load('visualization', '1', {'packages':['corechart']});
		google.load("maps", "3", {other_params: 'sensor=false'});
	</script>
	<script type='text/javascript' src='<?php echo plugins_url('/WP-GPX-Maps.js', __FILE__) ?>'></script>	
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
	if ($val == '')
	{
		$val = $defaultValue;
	}
	return $val;
}

function handle_WP_GPX_Maps_Shortcodes($attr, $content='')
{

	$gpx = findValue($attr, "gpx", "", "");
	$w =   findValue($attr, "width", "wpgpxmaps_width", "100%");
	$mh =  findValue($attr, "mheight", "wpgpxmaps_height", "450px");
	$mt =  findValue($attr, "mtype", "wpgpxmaps_map_type", "HYBRID");
	$gh =  findValue($attr, "gheight", "wpgpxmaps_graph_height", "200px");
	$showW = findValue($attr, "waypoints", "wpgpxmaps_show_waypoint", false);
	$donotreducegpx = findValue($attr, "donotreducegpx", "wpgpxmaps_donotreducegpx", false);
	$pointsoffset = findValue($attr, "pointsoffset", "wpgpxmaps_pointsoffset", 10);
	$uom =  findValue($attr, "uom", "wpgpxmaps_unit_of_measure", "0");
	$color_map =  findValue($attr, "mlinecolor", "wpgpxmaps_map_line_color", "#3366cc");
	$color_graph =  findValue($attr, "glinecolor", "wpgpxmaps_graph_line_color", "#3366cc");

	$r = rand(1,5000000);
	
	$sitePath = sitePath();
	
	$gpx = trim($gpx);
	
	if (strpos($gpx, "http://") !== 0)
	{
		$gpx = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $gpx);
		$gpx = $sitePath . $gpx;
	}
	else
	{
		$gpx = downloadRemoteFile($gpx);
	}
	
	$points = getPoints( $gpx, $pointsoffset, $donotreducegpx);
	$points_maps = '';
	$points_graph = '';
	$waypoints = '';

	foreach ($points as $p) {
		$points_maps .= '['.(float)$p[0].','.(float)$p[1].'],';
		
		if ($uom == '1')
		{
			// Miles and feet
			$points_graph .= '['.((float)$p[3]*0.000621371192).','.((float)$p[2]*3.2808399).'],';	
		}
		else
		{
			$points_graph .= '['.(float)$p[3].','.(float)$p[2].'],';		
		}
	}
	
	if ($showW == true)
	{
		$wpoints = getWayPoints($gpx);
		foreach ($wpoints as $p) {
			$waypoints .= '['.(float)$p[0].','.(float)$p[1].',\''.unescape($p[4]).'\',\''.unescape($p[5]).'\',\''.unescape($p[7]).'\'],';
		}
	}
	
	$p="/,$/";
	$points_maps = preg_replace($p, "", $points_maps);
	$points_graph = preg_replace($p, "", $points_graph);			
	$waypoints = preg_replace($p, "", $waypoints);
	
	if (preg_match("/^(\[0,0\],?)+$/", $points_graph)) 
	{		
		$points_graph = "";	
	} 

	$output = '
		<div id="wpgpxmaps_'.$r.'" style="clear:both;">
			<div id="map_'.$r.'" style="width:'.$w.'; height:'.$mh.'"></div>
			<div id="chart_'.$r.'" class="plot" style="width:'.$w.'; height:'.$gh.'"></div>
		</div>
		<script type="text/javascript">
			var m_'.$r.' = ['.$points_maps.'];
			var c_'.$r.' = ['.$points_graph.'];	
			var w_'.$r.' = ['.$waypoints.'];	
			wpgpxmaps("'.$r.'","'.$mt.'",m_'.$r.',c_'.$r.', w_'.$r.', "'.$uom.'", "'.$color_map.'", "'.$color_graph.'");
		</script>';	
	
	return $output;
}

function downloadRemoteFile($remoteFile)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $remoteFile); 
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
	add_option('wpgpxmaps_pointsoffset','10','','yes');
	add_option('wpgpxmaps_donotreducegpx','true','','yes');
	add_option("wpgpxmaps_unit_of_measure", 'mt', '', 'yes');
	add_option("wpgpxmaps_graph_line_color", '#3366cc', '', 'yes');
	add_option("wpgpxmaps_map_line_color", '#3366cc', '', 'yes');
}

function WP_GPX_Maps_remove() {
	delete_option('wpgpxmaps_width');
	delete_option('wpgpxmaps_graph_height');
	delete_option('wpgpxmaps_height');
	delete_option('wpgpxmaps_map_type');
	delete_option('wpgpxmaps_show_waypoint');
	delete_option('wpgpxmaps_pointsoffset');
	delete_option('wpgpxmaps_donotreducegpx');
	delete_option('wpgpxmaps_unit_of_measure');
	delete_option('wpgpxmaps_graph_line_color');
	delete_option('wpgpxmaps_map_line_color');
	
}

?>
