<?php if(!defined('PmWiki'))exit(); // Time-stamp: <2012-07-04 17:20:12 tamara>
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
 * INSTALLATION
 * ============
 *
 * To install this recipe, download it and copy it into your wiki's
 * cookbook directory, then add the following lines to your local/config.php
 * file:
 *
 *   $EnableEmbedTweet=true;
 *   include_once("$FarmD/cookbook/embedtweet.php");
 *
 * USAGE
 * =====
 *
 * To use, simply include the following in your wiki page text:
 *
 *    [tweet id=<status id of tweet>]
 *
 * Alternately, you can use the URL of the particular status:
 *
 *    [tweet url=https://twitter.com/<user>/status/<status code>]
 *
 * Both of these can be obtained for a tweet on the twitter web site
 * by clicking on the "Expand" link in the tweet, then on "Embed this Tweet"
 * and selecting Link tab. The status id is the last string of digits
 * in the path. If you choose to url method, use the entire link
 * shown.
 *
 * Additional parameters are described on the API document at
 * <https://dev.twitter.com/docs/api/1/get/statuses/oembed>
 * but note that the omit_script is always set to true and the
 * script is linked in the html footer automatically by the recipe.
 *
 * For future-proofing, there are two customizable variables that
 * deal with the functionality provided by twitter.
 *
 *   $ETweet_API_URL is the url of API call that returns the
 *   contents of the tweet desired.
 *
 *   $ETweet_Widget_Script is the HTML that will include the
 *   widget script from twitter in the page footer.
 *
 * Neither of these should be set or changed unless the twitter API
 * changes.
 *
 * TODO:
 * * Provide a means of saving the tweet instead of fetching it each time.
 *   This will also prevent losing the tweet in the case it disappears
 *   from twitter, or twitter is inaccessible for some reason.
 *
 * NOTE:
 * * Some twitter feeds are inaccessible by outside sources, unless
 *   the caller is an authenticated user, and is allowed to view
 *   the tweet. Currently, there is nothing in twitter's oembed API
 *   that can get around this.
 */

// Version of this recipe
$RecipeInfo['EmbedTweet']['Version'] = '2012-07-04'; 

// Add a custom page storage location and some bundled wikipages.
//@include("EmbedTweet/bundlepages.php");

SDV($EnableEmbedTweet,0); // set $EnableEmbedTweet=1; in local/config.php
SDV($ETweet_API_URL,'https://api.twitter.com/1/statuses/oembed.json');
SDV($ETweet_Widget_Script,'<script src="http://platform.twitter.com/widgets.js" charset="utf-8"></script>');

$HTMLFooterFmt[] = $ETweet_Widget_Script;

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
  $tweet = ETw_FetchTweet($args);
  $embed = ETw_FormatTweet($tweet);
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