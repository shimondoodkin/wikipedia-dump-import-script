<?php

# downloadlist.php by Shimon Doodkin <helpmepro1@gmail.com> https://github.com/shimondoodkin/wikipedia-dump-import-script/

# usage like:
# php downloadlist.php > aaa
# nano aaa # delete all that not needed with control+k
# maybe use "screen" command
# bash aaa 


$lasturl="";

#$lasturl="http://dumps.wikimedia.your.org/enwiki/20160204/";



// http://dumps.wikimedia.your.org/backup-index.html - list of wikis, find there enwiki
//http://dumps.wikimedia.your.org/enwiki/20160204/
//http://dumps.wikimedia.your.org/enwiki/20160204/


function encodeURIComponent($str) {
    $revert = array('%21'=>'!', '%2A'=>'*', '%27'=>"'", '%28'=>'(', '%29'=>')');
    return strtr(rawurlencode($str), $revert);
}


class curl_onHeaders
{
	public $result=array();
	function onHeader( $curl, $header_line ) {
		$this->result[]=$header_line;
		return strlen($header_line);
	}
}
	
function curl($method,$url,$data=false,$headers=false)
{
  // public domain, by Shimon Doodkin <helpmepro1@gmail.com>
  // https://gist.github.com/shimondoodkin/c6e5f8f6c237444fdef6
    
	//$method="PUT"
	//$url ="http://example.com";
	//$data = "The updated text message";
	//$headers=array();  $headers[] = 'Accept: text/html';
	$ch = curl_init();
	if($data!==false)
	{
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch,CURLOPT_POSTFIELDS,$data); // any post data, a string like param1=a&param2=b
	}
	if($headers!==false)
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);  
	curl_setopt($ch, CURLOPT_URL,$url);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);  //for updating we have to use PUT method.
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    
	
	$onHeader = new curl_onHeaders();
	curl_setopt($ch, CURLOPT_HEADERFUNCTION, array(&$onHeader, 'onHeader'));

	$result = curl_exec($ch);
	$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	
    $object = new stdClass();
    $object->result = $result;
	$object->code = $httpCode;
	$object->headers = $onHeader->result;
	
	if(curl_errno($ch))
	{	curl_close($ch);

		throw new Exception("curl error: ".  curl_error($ch)); 
			  //$object->error =curl_error($ch);
	}
	curl_close($ch);
			  
	return $object;
}


 

function getcookies($headers)
{
	$cookies='';
	foreach( $headers as $header)
	{
		if (preg_match('/^Set-Cookie:\s*([^;]*)/mi', $header, $cookie) == 1)
		{
			if($cookies==='')
				$cookies = $cookie[1];
			else
				$cookies .="; ".$cookie[1];
		}
	}
	return $cookies;
}

function mearge_cookies($old,$new)
{
	// cookies like session are sent only once from server, multiple cookies generally can be mearged  with "; "
	// a cookie jar is prefered  but this code generally fine.
	// folowing code does not handle expires
	//
	// cookie format: https://msdn.microsoft.com/en-us/library/windows/desktop/aa384321(v=vs.85).aspx
	//
	// Set-Cookie: <name>=<value>[; <name>=<value>]...
    // [; expires=<date>][; domain=<domain_name>]
    // [; path=<some_path>][; secure][; httponly]
	//
	// <date> format:
	// DAY, DD-MMM-YYYY HH:MM:SS GMT
	// DAY The day of the week (Sun, Mon, Tue, Wed, Thu, Fri, Sat).
	// DD The day in the month (such as 01 for the first day of the month).
	// MMM The three-letter abbreviation for the month (Jan, Feb, Mar, Apr, May, Jun, Jul, Aug, Sep, Oct, Nov, Dec).
	// YYYY The year.
	// HH The hour value in military time (22 would be 10:00 P.M., for example).
	// MM The minute value.
	// SS The second value.


	$cookiesa=array();
	$cookies_strs_to_merge=array($old,$new);
	foreach($cookies_strs_to_merge as $cookies_str)
	{
		foreach(preg_split("/\\s*;\\s*/",$cookies_str) as $cookie)
		{
		 $pcookie=preg_split("/\\s*=\\s*/",$cookie);
		 $cookie_name=$pcookie[0];
		 $cookie_value=$pcookie[1];
		 if(sizeof($pcookie)>1)
		 {
			 if($cookie_name=='domain') continue;
			 if($cookie_name=='expires') continue;
			 if($cookie_name=='path') continue;
			$cookiesa[$cookie_name]=$cookie_value;
		 }
		 else if($cookie=='secure' )continue;
		 else if($cookie=='httponly' )continue;
		}
	}
	$cookies='';
	foreach($cookiesa as $cookie_name=>$cookie_value)
	 $cookies.=($cookies===''?'':'; ').$cookie_name.'='.$cookie_value;
 return $cookies;
}
//echo mearge_cookies("aaa=vsdfvsdfv; bbb=asdfasdfasf","aaa=222; ccc=123123"); die;

//$res=curl("GET",'http://doodkin.com');
//$lastcookies=getcookies($res->headers);
//$res=curl("GET",'http://doodkin.com',false,array('Cookie: '.$lastcookies));
//$lastcookies=mearge_cookies($lastcookies, getcookies($res->headers) );



$wiki="enwiki";

   $page_of_list_of_all_wiki_last_dumps=curl("GET",'http://dumps.wikimedia.your.org/backup-index.html')->result  ;
   preg_match_all("/href=\"($wiki\/.+?)\"/", $page_of_list_of_all_wiki_last_dumps , $output_array);

   $exampleurl="http://dumps.wikimedia.your.org/".$output_array[1][0]."/";

echo "# latest url: $exampleurl \n"; 
echo "# selected url: $lasturl \n"; 

if($lasturl=="")
{
	echo "set \$lasturl at top of script, to latest url";
	exit;
}
$page_of_all_dumps_of_a_wiki=curl("GET",$lasturl)->result;


//preg_match_all("/href=\"(.+?\\.sql\\.gz)\".+?<\\/a> (.+?)<\\/li>/", $page_of_all_dumps_of_a_wiki , $output_array);
//$output_array_pairs = array_map(null, $output_array[1], $output_array[2]); // with null as function, this makes  from: a[],b[]  that: [ [a,b] ]  
//$all_sql_files_urls= array_map ( function ($pair){ return "wget http://dumps.wikimedia.your.org".$pair[0]."  ## ".$pair[1]; } , $output_array_pairs ) ;

preg_match_all("/href=\"(.+?\/.+?\/(.+?-.+?-(.+?)\.sql\.gz))\".+?<\/a> (.+?)<\/li>/", $page_of_all_dumps_of_a_wiki, $output_array);
$all_sql_files=$output_array[2];
$all_sql_tables=$output_array[3];
$output_array_pairs = array_map(null, $output_array[1], $output_array[4]); // with null as function, this makes  from: a[],b[]  that: [ [a,b] ]  
$all_sql_files_urls= array_map ( function ($pair){ return "wget http://dumps.wikimedia.your.org".$pair[0]."  ## ".$pair[1]; } , $output_array_pairs ) ;


preg_match("/(.+\/).+$/", "http://dumps.wikimedia.your.org". $output_array[1][0]  , $output_array);
$all_sql_files_urls_prefix=$output_array[1];


preg_match_all("/href=\"(.+?-pages-articles.+?\\.xml-p.+?bz2)\".+?<\\/a> (.+?)<\\/li>/", $page_of_all_dumps_of_a_wiki , $output_array);
$output_array_pairs = array_map(null, $output_array[1], $output_array[2]); // with null as function, this makes  from: a[],b[]  that: [ [a,b] ]   

$pages_articles_xml_urls= array_map ( function ($pair){ return "wget http://dumps.wikimedia.your.org".$pair[0]."  ## ".$pair[1]; } , $output_array_pairs ) ;

$pages_articles_xml_urls_stdout= array_map ( function ($pair){ return "wget http://dumps.wikimedia.your.org".$pair[0]."  -O - "; } , $output_array_pairs ) ;


preg_match_all("/href=\"\\/.+?\\/.+?\\/(.+?-pages-articles.+?\\.xml-p.+?bz2)\".+?<\\/a> (.+?)<\\/li>/", $page_of_all_dumps_of_a_wiki , $output_array);
$pages_articles_xml_files= array_map ( function ($a){ return "pv ".$a." | lbzip2 -cd | java -Xmx512m -Xms128m -XX:NewSize=32m -XX:MaxNewSize=64m -XX:SurvivorRatio=6 -XX:+UseParallelGC -XX:GCTimeRatio=9 -XX:AdaptiveSizeDecrementScaleFactor=1 -server -jar /root/wp-download/mwdumper/mwdumper/target/mwdumper-1.25.jar --output=mysql://127.0.0.1/enwiki?user=website\\&password=JJHex3pBjpsbTyVy  --format=sql:1.25"; } , $output_array[1] ) ;

$pages_articles_xml_files_direct= array_map ( function ($a){ return  $a." | lbzip2 -cd | java -Xmx512m -Xms128m -XX:NewSize=32m -XX:MaxNewSize=64m -XX:SurvivorRatio=6 -XX:+UseParallelGC -XX:GCTimeRatio=9 -XX:AdaptiveSizeDecrementScaleFactor=1 -server -jar /root/wp-download/mwdumper/mwdumper/target/mwdumper-1.25.jar --output=mysql://127.0.0.1/enwiki?user=website\\&password=JJHex3pBjpsbTyVy  --format=sql:1.25"; } , $pages_articles_xml_urls_stdout ) ;

echo "\n\n"; 
echo join("\n",$all_sql_files_urls  ); 
echo "\n\n";
echo join("\n",$pages_articles_xml_urls );
echo "\n\n";    



?>



date > import-timing.txt
for table in \
      <?php echo join("  ",$all_sql_tables ); ?> ; \
  do
    echo "TRUNCATE TABLE $table ; " | mysql -B enwiki
done

for f in  \
    <?php echo join("  ",$all_sql_files ); ?> ; \
  do
    ( \
      LINE=$(zcat $f|head -n 200|awk '/-- Dumping data for table/ {print FNR}') #SKIP CRATE TABLES, also see "|tail -n +$LINE" below
      echo "SET autocommit=0; SET unique_checks=0; SET foreign_key_checks=0;" ; \
      pv $f | pigz -dc  |tail -n +$LINE;  \
      echo "SET autocommit=1; SET unique_checks=1; SET foreign_key_checks=1;" \
    ) | mysql -B enwiki
done
date >> import-timing.txt


<?php





?>

##stright from web version

date > import-timing.txt
for table in \
      <?php echo join("  ",$all_sql_tables ); ?> ; \
  do
    echo "TRUNCATE TABLE $table ; " | mysql -B enwiki
done

for f in  \
    <?php echo join("  ",$all_sql_files ); ?> ; \
  do
    ( \
      LINE=$(wget <?php echo $all_sql_files_urls_prefix?>$f -O -|gunzip|head -n 200|awk '/-- Dumping data for table/ {print FNR}') #SKIP CRATE TABLES, also see "|tail -n +$LINE" below
	  FSIZE=$(wget <?php echo $all_sql_files_urls_prefix?>$f --spider --server-response -O - 2>&1 | sed -ne '/Content-Length/{s/.*: //;p}')
      echo "SET autocommit=0; SET unique_checks=0; SET foreign_key_checks=0;" ; \
      wget <?php echo $all_sql_files_urls_prefix?>$f --show-progress -O - | pigz -dc  |tail -n +$LINE;  \
      echo "SET autocommit=1; SET unique_checks=1; SET foreign_key_checks=1;" \
    ) | mysql -B enwiki
done
date >> import-timing.txt


<?php

                                                         
echo join("\n",$pages_articles_xml_files );
echo "\n\n";
echo "\n\n";
echo "#DIRECT IMPORT\n\n";
echo "\n\n";
echo join("\n",$pages_articles_xml_files_direct );




?>
