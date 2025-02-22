<?php
      use Mf2;
	function curldo($url){
		$curl_handle=curl_init();
		curl_setopt($curl_handle,CURLOPT_URL,$url);
		curl_setopt($curl_handle,CURLOPT_RETURNTRANSFER,1);
		$buffer = curl_exec($curl_handle);
		curl_close($curl_handle);
		return $buffer;
	}

	function rss_imageget($fulltext){
		$dom = new DOMDocument();
		$dom->loadXML($fulltext);
		$image = $dom->getElementsByTagName('image');
		if(!$head->length >0){
			return "";
		}else{
			$image = $image->item(0);
			$url = $image->getElementsByTagName('url');
			if($url->length >0){
				foreach ($url as $item) {
					if(strlen($item->nodeValue) > 0){
						return $item->nodeValue;
					}
					return '';
				}
			}else{
				return "";
			}
		}
	}



	function html_titleget($fulltext){
		$dom = new DOMDocument();
		@$dom->loadHTML($fulltext);
		$head = $dom->getElementsByTagName('head');
		if(!$head->length >0){
			return "";
		}else{
			$head = $head->item(0);

			$title = $head->getElementsByTagName('title');
			if($title->length >0){
				$title = $title->item(0)->nodeValue;
				return $title;
			}else{
				return "";
			}
		}
	}



	function rss_linkget($fulltext){
		$dom = new DOMDocument();
		$dom->loadXML($fulltext);
		$head = $dom->getElementsByTagName('link');
		echo "<br/>links now: ";
		foreach ($head as $item) {
			echo $item->nodeValue . "\n";
			if(strlen(trim($item->nodeValue)) > 0){
				$r = $item->nodeValue;
				return $r;
			}
		}
		return '';
	}



	function rss_hubget($fulltext){
		$dom = new DOMDocument();
		$dom->loadXML($fulltext);
		$head = $dom->getElementsByTagName('link');
		echo "<br/>hubs now: ";
		foreach ($head as $item) {
			if($item->hasAttribute("href")){
				echo $item->getAttribute("href");
				if($item->getAttribute("rel") == "hub"){
					return $item->getAttribute("href");
				}
			}
		}
		return '';
	}

	function rss_selfget($fulltext){
		$dom = new DOMDocument();
		$dom->loadXML($fulltext);
		$head = $dom->getElementsByTagName('link');
		echo "<br/>self now: ";
		foreach ($head as $item) {
			if($item->hasAttribute("href")){
				echo $item->getAttribute("href");
				if($item->getAttribute("rel") == "self"){
					return $item->getAttribute("href");
				}
			}
		}
		echo '<br/>no self found in links';
		
		$head = $dom->getElementsByTagName('status');
		echo "<br/>self now: ";
		foreach ($head as $item) {
			if($item->hasAttribute("feed")){
				return $item->getAttribute("feed");
			}
		}
		return '';
	}


	function getRSSLocation($html, $location){
		if(!$html or !$location){
			return false;
		}else{
			#search through the HTML, save all <link> tags
			# and store each link's attributes in an associative array
			preg_match_all('/<link\s+(.*?)\s*\/?>/si', $html, $matches);
			$links = $matches[1];
			$final_links = array();
			$link_count = count($links);
			for($n=0; $n<$link_count; $n++){
				$attributes = preg_split('/\s+/s', preg_replace_callback('`="(.*?)"`','convertAttributeWhitespace',$links[$n]));
				foreach($attributes as $attribute){
					$att = preg_split('/\s*=\s*/s', $attribute, 2);
					if(isset($att[1])){
						$att[1] = preg_replace('/([\'"]?)(.*)\1/', '$2', $att[1]);
						$final_link[strtolower($att[0])] = $att[1];
					}
				}
				$final_links[$n] = $final_link;
			}
			#print_r($final_links);
			#now figure out which one points to the RSS file
			for($n=0; $n<$link_count; $n++){
				if(strpos(strtolower($final_links[$n]['rel']), 'alternate') !== false){
					if(strtolower($final_links[$n]['type']) == 'application/rss+xml'){
						$href = $final_links[$n]['href'];
					}
					if(!$href and strtolower($final_links[$n]['type']) == 'application/atom+xml'){
						#kludge to make the first version of this still work
						$href = $final_links[$n]['href'];
					}
					if(!$href and strtolower($final_links[$n]['type']) == 'text/xml'){
						#kludge to make the first version of this still work
						$href = $final_links[$n]['href'];
					}
					
					if($href){
						if(strstr($href, "http://") !== false){ #if it's absolute
							$full_url = $href;
						}else{ #otherwise, 'absolutize' it
							$url_parts = parse_url($location);
							#only made it work for http:// links. Any problem with this?
							$full_url = "http://$url_parts[host]";
							if(isset($url_parts['port'])){
								$full_url .= ":$url_parts[port]";
							}
							if($href{0} != '/'){ #it's a relative link on the domain
								$full_url .= dirname($url_parts['path']);
								if(substr($full_url, -1) != '/'){
									#if the last character isn't a '/', add it
									$full_url .= '/';
								}
							}
							$full_url .= $href;
						}
						return $full_url;
					}
				}
			}
			return false;
		}
	}


	function WFCore_newfollow($examineurl){
		if(!preg_match('`(http|https)://.*`',$examineurl)){
		$examineurl = 'http://'.$examineurl;
		}
		$buffer = curldo($examineurl);
		if (empty($buffer)){
		echo "Error: Could not access ".$examineurl;
		}
		$hub = '';
		$image='';
		if(preg_match("`(\<![^\>]*\>|)\<(rss|atom|feed) `i",$buffer)){
			echo $examineurl." is a feed!";
			$followurl = rss_linkget($buffer);
			$hub = rss_hubget($buffer);
			$image = rss_imageget($buffer);
			$fulltext = curldo($followurl);
			$followtitle = html_titleget($fulltext);
			$followrss = $examineurl;


		}else{
			$discovery = getRSSLocation($buffer,$examineurl);
			if($discovery){
				$rssgot = curldo($discovery);
				$followurl = $examineurl;
				$followtitle = html_titleget($buffer);
				$followrss = $discovery;
				$hub = rss_hubget($rssgot);
				$image = rss_imageget($rssgot);
			}else{
				echo "there are no feeds here!";
				return;
			}

		}
		$linkdata = array(
		"link_url" => $followurl, // varchar, the URL the link points to
		"link_name"	=> $followtitle, // varchar, the title of the link
		"link_image" => $image, // varchar, a URL of an image
		"link_target" => '_blank', // varchar, the target element for the anchor tag
		"link_rss" => $followrss, // varchar, a URL of an associated RSS feed
		);
		if(strlen($hub) > 0){
			whisperfollow_subscribe_to_push($followrss,$hub);
		}
		
		if($link_id <1){
			echo "there was a problem adding the link";
		}else{
			echo "subscribed to ".$followtitle."!";
		}
		return $linkdata;
	}


		function whisperfollow_mf2_read($page){
			whisperfollow_log("<br/>MF2 Parsing ".$page."<br/>");

			$output = Mf2\parse(curldo($page),$page);
			foreach($output->items as $item){
				if($item->type == "h-feed"){
					foreach($item->children as $child){
						if(in_array("h-entry",$child->type)){
							try{
								whisperfollow_log("<br/>got ".$child->properties->name[0]." from ". $item->properties->name[0]."<br/>");
								add_whisper($child->properties->url[0],$child->properties->name[0],$child->properties->content[0]->html,$item->properties->name[0],$page,strtotime($item->properties->published[0])->format( 'U' ));
							}catch(Exception $e){
								whisperfollow_log("Exception occured: ".$e->getMessage());
							}
						}
					}
				}
			}
		}

		function whisperfollow_aggregate($feeds,$pushed=false){
			whisperfollow_log("Aggregating feeds:".count(feeds));
			if ( !empty( $feeds ) ) {
				
				//whisperfollow_log('<br/>feeds: '.print_r($feeds,true));
				
			
				add_filter( 'wp_feed_cache_transient_lifetime', 'whisperfollow_feed_time' );
				$feed = whisperfollow_fetch_feed( $feeds );
				if(is_wp_error($feed)){
					whisperfollow_log($feed->get_error_message());
					trigger_error($feed->get_error_message());
					whisperfollow_log("Feed read Error: ".$feed->get_error_message());
				}
				$feed->enable_cache(false);
				$feed->strip_htmltags(false);   
				
				//whisperfollow_log("<br/>Feed object:");
				//whisperfollow_log(print_r($feed,true));
				$items = $feed->get_items();

				//whisperfollow_log(substr(print_r($items,true),0,500));
				//whisperfollow_log("<br/>items object:");
				usort($items,'date_sort');
				foreach ($items as $item){
					try{
						whisperfollow_log("<br/>got ".$item->get_title()." from ". $item->get_feed()->get_title()."<br/>");
						add_whisper($item->get_permalink(),$item->get_title(),html_entity_decode ($item->get_description()),$item->get_feed()->get_title(),$item->get_feed()->get_link(),$item->get_date("U"));
					}catch(Exception $e){
						whisperfollow_log("Exception occured: ".$e->getMessage());
					}
				}
				
				remove_filter( 'wp_feed_cache_transient_lifetime', 'whisperfollow_feed_time' );
			}
			whisperfollow_log('No feed defined');
			
		}

		function whisperfollow_fetch_feed($url) {

			require_once (ABSPATH . WPINC . '/class-feed.php');

			$feed = new SimplePie();
			if(is_array($url)||preg_match("`^http://.*?`",$url)){
				whisperfollow_log("Url is fetchable");
				$feed->set_feed_url($url);
				$feed->set_cache_class('WP_Feed_Cache');
				$feed->set_file_class('WP_SimplePie_File');
				$feed->set_cache_duration(30);
				$feed->enable_cache(false);
				$feed->set_useragent('Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/535.7 (KHTML, like Gecko) Chrome/16.0.912.77 Safari/535.7');//some people don't like us if we're not a real boy
			}else{
			
				whisperfollow_log("Url is not fetchable");
				$feed->set_raw_data($url);
			}
			$feed->init();
			$feed->handle_content_type();
			
			//whisperfollow_log("Feed:".print_r($feed,true));

			if ( $feed->error() )
				$errstring = implode("\n",$feed->error());
				//if(strlen($errstring) >0){ $errstring = $feed['data']['error'];}
				if(stristr($errstring,"XML error")){
					whisperfollow_log('simplepie-error-malfomed: '.$errstring.'<br/><code>'.htmlspecialchars ($url).'</code>');
				}elseif(strlen($errstring) >0){
					whisperfollow_log('simplepie-error: '.$errstring);
				}else{
					//whisperfollow_log('simplepie-error-empty: '.print_r($feed,true).'<br/><code>'.htmlspecialchars ($url).'</code>');
				}
			return $feed;
		}
		
		
		function whisperfollow_pubsub_http($url, $post_string) {
			
		// add any additional curl options here
		$options = array(CURLOPT_URL => $url,
						 CURLOPT_USERAGENT => "PubSubHubbub-Subscriber-PHP/1.0",
						 CURLOPT_RETURNTRANSFER => true);
						 
		if ($post_string) {
			$options[CURLOPT_POST] = true;
			$options[CURLOPT_POSTFIELDS] = $post_string;
		}

		$ch = curl_init();
		curl_setopt_array($ch, $options);

		$response = curl_exec($ch);
		$info = curl_getinfo($ch);
		whisperfollow_log("PuSH Request (".$url."): ".print_r($options,true));

		whisperfollow_log("PuSH Response: ".$response."<br/>\n".print_r($info,true));
		// all good -- anything in the 200 range 
		if (substr($info['http_code'],0,1) == "2") {
			return $response;
		}
		return false;   
	}
		
	function whisperfollow_pubsub_change_subscription($mode, $topic_url, $hub) {
		if (!isset($topic_url))
			throw new Exception('Please specify a topic url');
			
		 // lightweight check that we're actually working w/ a valid url
		 if (!preg_match("|^https?://|i",$topic_url)) 
			throw new Exception('The specified topic url does not appear to be valid: '.$topic_url);
		
		// set the mode subscribe/unsubscribe
		$data = array("hub.mode"=>$mode,"hub.callback"=>site_url().'/index.php?wfpushend=endpoint',"hub.verify"=>"sync","hub.topic"=>$topic_url);
		//$data = "hub.mode=".$mode."&hub.callback=".urlencode(site_url()).'/index.php?wfpushend=true'."&hub.topic=".urlencode($topic_url);
		
		// make the http post request and return true/false
		// easy to over-write to use your own http function
		whisperfollow_pubsub_http($hub,$data);
	}

	


?>