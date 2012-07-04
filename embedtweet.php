<?php if(!defined('PmWiki'))exit(); // Time-stamp: <2012-07-04 16:31:17 tamara>
/** embedtweet.php
 *
 * Copyright (C) 2012 by Tamara Temple
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 * \author    Tamara Temple <tamara@tamaratemple.com>
 * \since     2012-07-04
 * \copyright 2012 by Tamara Temple
 * \license   GPLv3
 * \version   0.1
 *
 */

// Version of this recipe
$RecipeInfo['EmbedTweet']['Version'] = '2012-07-04'; 

// Add a custom page storage location and some bundled wikipages.
//@include("EmbedTweet/bundlepages.php");

SDV($EnableEmbedTweet,0); // set $EnableEmbedTweet=1; in local/config.php
SDV($ETweet_API_URL,'https://api.twitter.com/1/statuses/oembed.json');

$HTMLFooterFmt[] = '<script src="http://platform.twitter.com/widgets.js" charset="utf-8"></script>'; // get the twitter widget in the page

Markup('EmbedTweet','<inline','/\\[tweet\s*(.*?)\\]/ei',"ETw_HandleTweet('$1')");
/**
 * Handle the embedding of the tweet
 *
 * @returns null
 * @author Tamara Temple <tamara@tamaratemple.com>
 * @param string $parms
 **/
function ETw_HandleTweet ($parms='')
{
  global $EnableEmbedTweet;
  if (!IsEnabled($EnableEmbedTweet,false)) return;
  if (empty($parms)) return;
  $args = ParseArgs($parms);
  @sms('args: ',$args,__FILE__,__LINE__,__FUNCTION__);
  $tweet = ETw_FetchTweet($args);
  @sms('tweet: ',$tweet,__FILE__,__LINE__,__FUNCTION__);
  $embed = ETw_FormatTweet($tweet);
  @sms('embed: ',$embed,__FILE__,__LINE__,__FUNCTION__);
  return(Keep($embed));
} // END function ETw_HandleTweet

/**
 * Fetch the tweet
 *
 * @returns JSON encoded tweet contents
 * @author Tamara Temple <tamara@tamaratemple.com>
 * @param array $args
 **/
function ETw_FetchTweet ($args)
{
  global $ETweet_API_URL;
  
  $ch = curl_init(ETw_BuildURL($args));
  curl_setopt_array($ch,
		    array(CURLOPT_FOLLOWLOCATION=>true,
			  CURLOPT_RETURNTRANSFER=>true,
			  CURLOPT_CONNECTTIMEOUT=>15,
			  CURLOPT_TIMEOUT=>30,
			  CURLOPT_USERAGENT=>"Mozilla/5.0",
			  CURLOPT_REFERER=>$ScriptUrl,
			  ));
  $response = curl_exec($ch);
  if (false === $response) {
    $response = array("errors"=>array(array("message"=>"curl error","code"=>curl_error($ch))));
    return json_encode($response);
  }
  return $response;
} // END function ETw_FetchTweet

/**
 * Format the JSON encoded tweet for embedding in page
 *
 * @returns string HTML to embed
 * @author Tamara Temple <tamara@tamaratemple.com>
 * @param string $tweet - JSON encoded tweet
 **/
function ETw_FormatTweet ($tweet)
{
  $decoded = json_decode($tweet,true);
  if (isset($decoded['errors'])) {
    // an error occured, return it
    $embed = $decoded['errors'][0]['message'] .
      "Error code: " .
      $decoded['errors'][0]['code'];
  } else {
    $embed = $decoded['html'];
    if (preg_match('!script src="//!',$embed)) {
      // screwy return from twitter
      $embed = preg_replace('!script src="//!','script src="http://',$embed);
    }
  }
  return $embed;
} // END function ETw_FormatTweet

/**
 * Build the URL needed to make the tweet request
 *
 * @returns string - url to send
 * @author Tamara Temple <tamara@tamaratemple.com>
 * @param array $args - arguments to twitter API
 **/
function ETw_BuildURL ($args)
{
  global $ETweet_API_URL;
  if (isset($args['id'])) {
    //prefer id over url
    $query['id'] = $args['id'];
  } elseif (isset($args['url'])) {
    // use url
    $query['url'] = $args['url'];
  } else {
    // neither id or url given
    return false;
  }
  $api_parms=array('maxwidth','hide_media','hide_thread','omit_script','align','related','lang');
  foreach ($args as $key => $value) {
    if (in_array($key,$api_parms))
      {
	$query[$key] = $value;
      }
  }

  $query['omit_script']='true';

  $q_str = http_build_query($query);
  $q_str = $ETweet_API_URL . '?' . $q_str;
  return $q_str;

} // END function ETw_BuildURL