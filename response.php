<?php
if (empty($_POST) || empty($_POST['search_term'])) die('Please enter the URL');

// googleのsitemap xmlを使ってurlを抽出する
//$google_xml_url = 'http://nob-log.info/sitemap.xml';
$google_xml_url = $_POST['search_term'];
$limit			= 20;

if (!isValidURL($google_xml_url)) die ('Please enter the URL');
if (!isValidXml($google_xml_url)) die ('Please supply the xml');

// urlのリストを作成する
$xml			= simplexml_load_file($google_xml_url);

foreach($xml->url as $data){
	$urls[] = $data->loc;
}

// fqlを作成する
$fql			= "SELECT url, like_count FROM link_stat WHERE url IN ('". implode("','", $urls). "')";
$fql_query_url	= "https://api.facebook.com/method/fql.query?format=json&query=".urlencode($fql);

$fql_query_result = file_get_contents($fql_query_url);
$fql_query_obj	= json_decode($fql_query_result, true);

if (empty($fql_query_obj)) die('not found');

foreach ($fql_query_obj as $key => $row) {
	$like_count[$key] = $row['like_count'];
}

array_multisort($like_count, SORT_DESC, $fql_query_obj);

$ranking = array_slice($fql_query_obj, 0, $limit);

$string	 = "<tr>\n";
$string	.= "<th>liked</th>\n";
$string	.= "<th>title</th>\n";
$string	.= "</tr>\n";

foreach ($ranking as &$val) {

	if ($val['like_count'] == 0) continue;

	$val['title'] = getPageTitle($val['url']);

	$string .= "<tr>";
	$string .= "<td>".$val['like_count']."</td>\n";
	$string .= '<td><a href="'.$val['url'].'" target="_blank">'.$val['title']."</a></td>\n";
	$string .= "</tr>\n";

}

echo $string;

function getPageTitle($url) {
	$html = file_get_contents($url); 
	$html = mb_convert_encoding($html, mb_internal_encoding(), "auto" ); 
	if ( preg_match( "/<title>(.*?)<\/title>/i", $html, $matches) ) { 
		return $matches[1];
	} else {
		return false;
	}
}

function isValidURL($url) {
	return preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $url);
}

function isValidXml($url) {

	$parse_url = parse_url($url);
	return (pathinfo($parse_url['path'], PATHINFO_EXTENSION) == 'xml');
}
