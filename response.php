<?php
if (empty($_POST) || empty($_POST['search_term'])) die('Please enter the URL');

require_once ('../libs/simplehtmldom/simple_html_dom.php');

// googleのsitemap xmlを使ってurlを抽出する
//$google_xml_url = 'http://nob-log.info/sitemap.xml';
$google_xml_url = $_POST['search_term'];
$limit			= 20;

if (!isValidURL($google_xml_url)) die ('Please enter the URL');
//if (!isValidXml($google_xml_url)) die ('Please supply the xml');

// urlのリストを作成する
$xml			= simplexml_load_file($google_xml_url);

foreach($xml->url as $data){

	if (is_object($data->loc)) {
		$url = (string) $data->loc;
	} else {
		$url = $data->loc;
	}

	// fqlを作成する
	$fql			= 'SELECT url, like_count FROM link_stat WHERE url IN " ' . $url .'"';
	$fql_query_url	= "https://api.facebook.com/method/fql.query?format=json&query=".urlencode($fql);

	$urls[] = $fql_query_url;
}

$fql_query_obj = getMultiContents($urls, 20);
//$fql_query_obj		= json_decode($fql_query_result, true);

if (empty($fql_query_obj)) die('not found');

foreach ($fql_query_obj as $key => $row) {
	$like_count[$key] = $row['like_count'];
}

array_multisort($like_count, SORT_DESC, $fql_query_obj);

$ranking = array_slice($fql_query_obj, 0, $limit);

$string	 = "<tr>\n";
$string	.= "<th>title</th>\n";
$string	.= "<th>liked</th>\n";
$string	.= "</tr>\n";

foreach ($ranking as &$val) {
	//if ($val['like_count'] == 0) continue;

	$val['title'] = (getPageTitle($val['url']));

	$string .= "<tr>";
	$string .= '<td><a href="'.$val['url'].'" target="_blank">'.$val['title']."</a></td>\n";
	$string .= "<td>".$val['like_count']."</td>\n";
	$string .= "</tr>\n";

}
echo $string;

exit;

function getPageTitle($url) {

	// なぜか file_get_contentsが動かないのでとりあえず…
	return $url;

	$html = file_get_contents($url); 
	$html = mb_convert_encoding($html, mb_internal_encoding(), "auto" ); 
	if ( preg_match( "/<title>(.*?)<\/title>/i", $html, $matches) ) { 
		return $matches[1];
	} else {
		return $url;
	}
}

function isValidURL($url) {
	return preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $url);
}

function isValidXml($url) {

	$parse_url = parse_url($url);
	return (pathinfo($parse_url['path'], PATHINFO_EXTENSION) == 'xml');
}



/** 
 * 複数URLのコンテンツ、及び通信ステータスを一括取得する。 
 * サンプル: 
 *   $urls = array( "http://〜", "http://〜", "http://〜" ); 
 *   $results = getMultiContents($urls); 
 *   print_r($results); 
 */  
function getMultiContents( $url_lists, $max ) {

	$results = array();

	foreach(array_chunk($url_lists, $max) as $url_list) {

		// マルチハンドルの用意  
		$mh = curl_multi_init();  

		// URLをキーとして、複数のCurlハンドルを入れて保持する配列  
		$ch_list = array();  

		// Curlハンドルの用意と、マルチハンドルへの登録  
		foreach( $url_list as $url ) {  
			$ch_list[$url] = curl_init($url);  
			curl_setopt($ch_list[$url], CURLOPT_RETURNTRANSFER, TRUE);  
			curl_setopt($ch_list[$url], CURLOPT_TIMEOUT, 1);  // タイムアウト秒数を指定  
			curl_multi_add_handle($mh, $ch_list[$url]);  
		}  

		// 一括で通信実行、全て終わるのを待つ  
		$running = null;  
		do { curl_multi_exec($mh, $running); } while ( $running );  

		// 実行結果の取得  
		foreach( $url_list as $url ) {  
			// ステータスとコンテンツ内容の取得  
			//$results[$url] = curl_getinfo($ch_list[$url]);  
			//$results[] = reset(json_decode(curl_multi_getcontent($ch_list[$url]), true));  
			$res = json_decode(curl_multi_getcontent($ch_list[$url]), true);

			if (!empty($res)) {
				$results[] = $res[0];
			}

			// Curlハンドルの後始末  
			curl_multi_remove_handle($mh, $ch_list[$url]);  
			curl_close($ch_list[$url]);  
		}  

		// マルチハンドルの後始末  
		curl_multi_close($mh);  
	}
	// 結果返却  
	return $results;  
}  

