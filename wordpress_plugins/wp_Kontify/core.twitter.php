<?php

// Main Class
require_once 'core.php';

class WpAutomaticTwitter extends wp_automatic
{

/**
 * docs: https://developer.twitter.com/en/docs/twitter-api/tweets/search/introduction
 * docs: https://developer.twitter.com/en/docs/twitter-api/tweets/search/integrate/build-a-query
 *
 */

    //public token
    public $access_token = '';
    public $current_item = array();

    public function twitter_fetch_items($keyword, $camp)
    {

        //report
        echo "<br>So I should now get some tweets from Twitter for Search :" . $keyword;

        //read bearer twitter token
        $wp_automatic_tw_bearer_token = $this->get_access_token();

        if ($wp_automatic_tw_bearer_token == '') {
            echo '<br><span style="color:red">Twitter Bearer token is required, please visit the settings page and add it</span>';
            return false;
        }

        // ini options
        $camp_opt = $this->camp_opt;
        $camp_general = $this->camp_general;

        // get start-index for this keyword
        $query = "select keyword_start ,keyword_id from {$this->wp_prefix}automatic_keywords where keyword_name='$keyword' and keyword_camp={$camp->camp_id}";
        $rows = $this->db->get_results($query);
        $row = $rows[0];
        $kid = $row->keyword_id;
        $start = $row->keyword_start;
        if ($start == 0) {
            $start = 1;
        }

        if ($start == -1) {
            echo '<- exhausted keyword';

            if (!in_array('OPT_IT_CACHE', $camp_opt)) {
                $start = 1;
                echo '<br>Cache disabled resetting index to 1';
            } else {

                //check if it is reactivated or still deactivated
                if ($this->is_deactivated($camp->camp_id, $keyword)) {
                    $start = 1;
                } else {
                    //still deactivated
                    return false;
                }

            }

        } elseif (!in_array('OPT_IT_CACHE', $camp_opt)) {
            $start = 1;
            echo '<br>Cache disabled resetting index to 1';
        }

        //good we now have a valid twitter token
        echo ' index:' . $start;

        // update start index to start+1
        $nextstart = $start + 1;
        $query = "update {$this->wp_prefix}automatic_keywords set keyword_start = $nextstart where keyword_id=$kid ";
        $this->db->query($query);

        //building the twitter url
        $query = wp_automatic_trim($keyword);

        $url = 'https://twitter-api45.p.rapidapi.com/search.php?search_type=Top&query=' ;

        $is_posting_by_user = false; //flag to check if posting by user

        //specific user from:beINSPORTS but not from:beINSPORTS filter:videos
        if (stristr($keyword, 'from:') && !stristr(wp_automatic_trim($keyword), ' ')) {

            $is_posting_by_user = true;

            $userKey = wp_automatic_str_replace('from:', '', $keyword);

            //https://twitter-api45.p.rapidapi.com/timeline.php?screenname=elonmusk
            $url = 'https://twitter-api45.p.rapidapi.com/timeline.php?screenname=' . urlencode($userKey);

        }

        //language lang:en lang:fr
        if (in_array('OPT_TW_LANG', $camp_opt)) {

            $cg_tw_lang = $camp_general['cg_tw_lang'];

            if (wp_automatic_trim($cg_tw_lang) != '') {
                $query .= ' lang:' . $cg_tw_lang;
            }
        }

        //skip rewtweet option OPT_TW_RT -is:retweet
        if (in_array('OPT_TW_RT', $camp_opt) && !stristr($query, 'is:retweet')) {
            $query .= ' -is:retweet';
        }

        //skip reply option OPT_TW_RE
        if (in_array('OPT_TW_RE', $camp_opt) && !stristr($query, 'is:reply')) {
            $query .= ' -is:reply';
        }

        //check if query contains  "  filter:" and replace it with " has:"
        if (stristr($query, 'filter:')) {

            echo '<br>Query has the term filter: Replacing filter: with has:, v2 API does not support filter:';
            $query = wp_automatic_str_replace('filter:', 'has:', $query);

            echo '<br>Modified Query:' . $query;

        }

        //report query
        if (!$is_posting_by_user) {
            echo '<br>Query:' . $query;

            //add the final query to the url
            $url .= urlencode($query);
        }

        //pagination
        // get requrest url from the zero index

        $next_token_field_name = 'wp_twitter_next_token_' . md5($keyword); //field name for the next token for pagination

        if ($start == 1) {

            //use first base query

        } else {

            //not first page get the bookmark

            $wp_tw_next_max_id = get_post_meta($camp->camp_id, $next_token_field_name, 1);

            if (wp_automatic_trim($wp_tw_next_max_id) == '') {
                echo '<br>No new page max id';

            } else {
                echo '<br>next_token:' . $wp_tw_next_max_id;

                //if posting by username set pagination token, otherwise set next token
                if ($is_posting_by_user) {
                    $url = $url . "&pagination_token=" . $wp_tw_next_max_id;
                } else {

                    $url = $url . "&next_token=" . $wp_tw_next_max_id;
                }

            }

        }

        //report url
        echo '<br>Twitter url:' . $url;

        //skip ssl
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);

        //authorize
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, [
            "x-rapidapi-host: twitter-api45.p.rapidapi.com",
            "x-rapidapi-key: " . $wp_automatic_tw_bearer_token,
        ]);
        curl_setopt($this->ch, CURLOPT_HTTPGET, 1);
        curl_setopt($this->ch, CURLOPT_URL, wp_automatic_trim($url));
        $exec = curl_exec($this->ch);
        $x = curl_error($this->ch);

        //read curl status code
        $httpcode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);

        echo '<br>http code:' . $httpcode;

        //verify if exec contains expected json
        if (!stristr($exec, '{')) {
            echo '<br>no json reply';
            return false;
        }

        //json decode reply
        $arr = json_decode($exec);

        //print_r($arr);
        //exit;

        //verify if status code is 200, if not return
        if ($httpcode != 200) {
            echo '<br>http code not 200';

            //if set arr->detail, echo
            if (isset($arr->message)) {
                //message in red
                echo '<br><span style="color:red">Twitter error:' . $arr->message . '</span>';
            }

            return false;
        }

        //print array

        //validating reply //old stristr($exec, 'search_metadata')   || (  stristr($keyword, 'from:') && stristr($exec, '{') && ! stristr($exec,'"errors"')  )
        $next_token = '';
        if (isset($arr->timeline)) {

            //next_token
            if (isset($arr->next_cursor)) {
                $next_token = $arr->next_cursor;
                echo '<br>Setting next_token:' . $next_token;
            }

            $items = $arr->timeline;

            //reverse
            if (in_array('OPT_PT_REVERSE', $camp_opt)) {
                echo '<br>Reversing order';
                $items = array_reverse($items);
            }

            echo '<ol>';

            //loop pins
            $i = 0;
            $max_id = 99999999999999999999999999999999999999;

            foreach ($items as $item) {

                //set the item public variable
                $this->current_item = $item;

                //reduced item array
                $itm = array();

                //increment
                $i++;

                //tweet id example 1878488800147439911
                $tweet_id = $this->get_item_id();

                //user id example 2226061
                $user_id = $this->get_item_author_id();

                //item url example https://x.com/2226061/statuses/1878488800147439911
                $itemUrl = $this->get_item_url();

                //max_id
                if ($tweet_id < $max_id) {
                    $max_id = $tweet_id;
                }

                $max_id_str = $tweet_id;

                //report

                echo '<li>' . $itemUrl;

                //check if retweet
                //check if item has referenced_tweets array and any of the array items has type retweeted
                $is_retweet = false;
                $retweeted_id = false; //id of the retweeted tweet

                if (isset($item->referenced_tweets)) {
                    foreach ($item->referenced_tweets as $referenced_tweet) {
                        if ($referenced_tweet->type == 'retweeted') {
                            $is_retweet = true;
                            $retweeted_id = $referenced_tweet->id;
                            break;
                        }
                    }
                }

                //skip if a retweet and option to skip retweets is set
                if (in_array('OPT_TW_RT', $camp_opt) && $is_retweet) {
                    echo '<-- Retweet skipping...';
                    continue;
                }

                //check if reply to
                if (in_array('OPT_TW_RE', $camp_opt)) {
                    if (isset($item->in_reply_to_user_id) && wp_automatic_trim($item->in_reply_to_user_id) != '') {
                        if ($item->in_reply_to_user_id != $item->user->id) {
                            echo '<-- Reply skipping...';
                        }

                        continue;
                    }
                }

                //build item\

                //If RT, replace the id with the retweeted id
                if ($is_retweet) {
                    $itm['item_id'] = $retweeted_id;
                } else {
                    $itm['item_id'] = $tweet_id;
                }

                // HASHTAG
                if (in_array('OPT_TW_TAG', $camp_opt)) {
                    if (isset($item->entities->hashtags) && (count($item->entities->hashtags) > 0)) {
                        $hashtags = $item->entities->hashtags;
                        $hashtagsArr = array();
                        foreach ($hashtags as $hashtag) {
                            $hashtagsArr[] = $hashtag->text;
                        }

                        $itm['item_hashtags'] = implode(',', $hashtagsArr);

                    }
                }

                $itm['item_url'] = $itemUrl;
                $itm['item_description'] = $item->text;

                //fix &amp;
                $itm['item_description'] = wp_automatic_str_replace('&amp;', '&', $itm['item_description']);

                //hyperlink links
                $itm['item_description'] = $this->hyperlink_this($itm['item_description']);

                //created at
                $itm['item_created_at'] = $item->created_at;

                //item_author_id
                $itm['item_author_id'] = $user_id;

                //item_author_name
                $itm['item_author_name'] = $this->get_item_author_name();

                //item_author_screen_name
                $itm['item_author_screen_name'] = $this->get_item_author_screen_name();

                //item_author_description
                $itm['item_author_description'] = $this->get_item_author_description();

                //user url from the username https://twitter.com/item_author_screen_name
                $itm['item_author_url'] = $this->get_item_author_url();

                //item_author_profile_image
                $itm['item_author_profile_image'] = $this->get_item_author_profile_image();

                //grab images and add them to the description
                $itm['item_image'] = '';
                $all_imgs = '';

                if (isset($item->entities) && isset($item->entities->media) && count($item->entities->media) > 0) {

                    foreach ($item->entities->media as $media_img) {

                        if ($media_img->type == 'photo') {
                            //good let's append it
                            $all_imgs .= '<img src="' . $media_img->media_url_https . '" /><br>';
                            $itm['item_image'] = $media_img->media_url_https;
                        } elseif ($media_img->type == 'video' && $itm['item_image'] == '') {

                            //preview_image_url
                            if (isset($media_img->media_url_https)) {

                                echo '<br>Video preview image found:' . $media_img->media_url_https;

                                $itm['item_image'] = $media_img->media_url_https;
                            }

                        }

                    }

                    $itm['item_description'] = $all_imgs . '<br><br>' . $itm['item_description'];
                }

                //item_video_url 'https://twitter.com/'.$itm['item_author_screen_name'].'/status/'.$itm['item_id']
                $temp['item_video_url'] = ''; //ini

                if (isset($item->media->video)) {

                    $vidURL = 'https://twitter.com/' . $itm['item_author_screen_name'] . '/status/' . $itm['item_id'];
                    $itm['item_video_url'] = $vidURL;

                }

                //direct video URL ex https://video.twimg.com/amplify_video/1575414620113846273/vid/720x1280/2B2ZElQrA8U_nM3I.mp4?tag=14
                $temp['item_video_url_direct'] = ''; //ini

                if (isset($item->media->video[0])) {
                    if (isset($item->media->video[0]->variants)) {

                        $variants = array();
                        $variants = ($item->media->video[0]->variants);

                        if (count($variants) > 0) {

                            $bitrate = 0;
                            $direct_video_url = '';
                            foreach ($variants as $varient) {
                                if (stristr($varient->content_type, 'video/') && $varient->bitrate > $bitrate) {
                                    $direct_video_url = $varient->url;
                                    $bitrate = $varient->bitrate;
                                }
                            }

                        }

                        if (wp_automatic_trim($direct_video_url) != '') {

                            if (stristr($direct_video_url, '?tag')) {
                                $direct_video_url_parts = explode('?tag', $direct_video_url);
                                $direct_video_url = $direct_video_url_parts[0];
                            }

                            $itm['item_video_url_direct'] = $direct_video_url;
                        }
                    }
                }

                //print
                print_r($itm);
                //exit;

                $data = base64_encode(serialize($itm));

                if ($this->is_execluded($camp->camp_id, $itm['item_url'])) {
                    echo '<-- Execluded';
                    continue;
                }

                //check if old
                $old_post_found = false;
                if (in_array('OPT_YT_DATE', $camp_opt)) {
                    if ($this->is_link_old($camp->camp_id, strtotime($itm['item_created_at']))) {
                        echo '<--old post execluding...';
                        $old_post_found = true;
                        continue;
                    } else {
                        echo ' <- created:' . $itm['item_created_at'];
                    }
                }

                if (!$this->is_duplicate($itm['item_url'])) {

                    $query = "INSERT INTO {$this->wp_prefix}automatic_general ( item_id , item_status , item_data ,item_type) values (    '{$itm['item_id']}', '0', '$data' ,'tw_{$camp->camp_id}_$keyword')  ";
                     $this->db->query($query);
                } else {
                    echo ' <- duplicated <a href="' . get_edit_post_link($this->duplicate_id) . '">#' . $this->duplicate_id . '</a>';
                }

                echo '</li>';

            }

            echo '</ol>';
            echo '<br>Total ' . $i . ' Tweets found & cached';

            //check if nothing found so deactivate
            if ($i == 0) {

                echo '<br>No new tweets found ';
                echo '<br>Keyword has no more tweets deactivating...';
                $query = "update {$this->wp_prefix}automatic_keywords set keyword_start = -1 where keyword_id=$kid ";
                $this->db->query($query);

                if (!in_array('OPT_NO_DEACTIVATE', $camp_opt)) {
                    $this->deactivate_key($camp->camp_id, $keyword);
                }

                //delete bookmark value
                delete_post_meta($camp->camp_id, $next_token_field_name);

            } else {

                //get max id
                if ($next_token != '') {
                    echo '<br>Updating next token' . $next_token;

                    echo '<br>Field name: ' . $next_token_field_name;

                    update_post_meta($camp->camp_id, $next_token_field_name, $next_token);

                    //reset pagination when posting from  a specific user
                    if ($old_post_found && stristr($keyword, 'from:')) {
                        echo '<br>Resetting pagination';
                        delete_post_meta($camp->camp_id, $next_token_field_name);
                    }

                } else {

                    echo '<br>No pagination found deleting next page index';
                    delete_post_meta($camp->camp_id, $next_token_field_name);

                }

            }

        } else {

            //no valid reply
            echo '<br>No Valid reply for twitter search <br>' . $exec;

        }

    }

//Twitter
    public function twitter_get_post($camp)
    {

        //token
        $wp_automatic_tw_bearer_token = $this->get_access_token();

        if ($wp_automatic_tw_bearer_token == '') {
            echo '<br><span style="color:red">Twitter Bearer token is required, please visit the settings page and add it</span>';
            return false;
        }

        //ini keywords
        $camp_opt = $this->camp_opt;
        $camp_general = $this->camp_general;

        //looping keywords
        $keywords = explode(',', $camp->camp_keywords);
        foreach ($keywords as $keyword) {

            //report keyword
            echo '<br>Processing Keyword:' . $keyword;

            //trim keyword
            $keyword = wp_automatic_trim($keyword);

            //update last keyword
            update_post_meta($camp->camp_id, 'last_keyword', wp_automatic_trim($keyword));

            //when valid keyword
            if (wp_automatic_trim($keyword) != '') {

                //record current used keyword
                $this->used_keyword = $keyword;

                // getting links from the db for that keyword
                $query = "select * from {$this->wp_prefix}automatic_general where item_type=  'tw_{$camp->camp_id}_$keyword' ";
                $res = $this->db->get_results($query);

                // when no links lets get new links
                if (count($res) == 0) {

                    //clean any old cache for this keyword
                    $query_delete = "delete from {$this->wp_prefix}automatic_general where item_type='tw_{$camp->camp_id}_$keyword' ";
                    $this->db->query($query_delete);

                    //get new links
                    $this->twitter_fetch_items($keyword, $camp);

                    // getting links from the db for that keyword
                    $res = $this->db->get_results($query);
                }

                //check if already duplicated
                //deleting duplicated items
                $res_count = count($res);
                for ($i = 0; $i < $res_count; $i++) {

                    $t_row = $res[$i];

                    $t_data = unserialize(base64_decode($t_row->item_data));

                    $t_link_url = $t_data['item_url'];

                    if ($this->is_duplicate($t_link_url)) {

                        //duplicated item let's delete
                        unset($res[$i]);

                        echo '<br>Tweet (' . $t_data['item_title'] . ') found cached but duplicated <a href="' . get_permalink($this->duplicate_id) . '">#' . $this->duplicate_id . '</a>';

                        //delete the item
                        $query = "delete from {$this->wp_prefix}automatic_general where id={$t_row->id} ";
                        $this->db->query($query);

                    } else {
                        break;
                    }

                }

                // check again if valid links found for that keyword otherwise skip it
                if (count($res) > 0) {

                    // lets process that link
                    $ret = $res[$i];

                    $temp = unserialize(base64_decode($ret->item_data));

                    //report link
                    echo '<br>Found Link:' . $temp['item_url'];

                    //generating title
                    if (@wp_automatic_trim($temp['item_title']) == '') {

                        if (in_array('OPT_IT_AUTO_TITLE', $camp_opt)) {

                            echo '<br>No title generating...';

                            $cg_it_title_count = $camp_general['cg_it_title_count'];
                            if (!is_numeric($cg_it_title_count)) {
                                $cg_it_title_count = 80;
                            }

                            //remove links
                            $cleanContent = preg_replace('{<a .*?a>}', '', $temp['item_description']);
                            $cleanContent = $this->removeEmoji($this->strip_urls(strip_tags($cleanContent)));

                            // remove hashtags
                            if (in_array('OPT_TW_NO_TTL_TAG', $camp_opt)) {
                                $cleanContent = preg_replace('{#\S*}', '', $cleanContent);
                            }

                            if (function_exists('mb_substr')) {
                                $newTitle = (mb_substr($cleanContent, 0, $cg_it_title_count));
                            } else {
                                $newTitle = (substr($cleanContent, 0, $cg_it_title_count));
                            }

                            // Clean RT's RT @GoogleStreetArt:
                            if (stristr($newTitle, 'RT') && in_array('OPT_IT_TITLE_CLEAN', $camp_opt)) {
                                echo '<br>Cleaning RT';
                                $newTitle = preg_replace('{RT @.*?: }', '', $newTitle);
                            }

                            if (in_array('OPT_GENERATE_TW_DOT', $camp_opt)) {
                                $temp['item_title'] = ($newTitle);
                            } else {
                                $temp['item_title'] = ($newTitle) . '...';
                            }
                            echo '<br>Generated title:' . $temp['item_title'];

                        } else {

                            $temp['item_title'] = '(notitle)';

                        }

                    }

                   //print temp
                    print_r($temp);
                    //exit;

                    // update the link status to 1
                    $query = "delete from {$this->wp_prefix}automatic_general where id={$ret->id}";
                    $this->db->query($query);

                    // if cache not active let's delete the cached items and reset indexes
                    if (!in_array('OPT_IT_CACHE', $camp_opt)) {

                        echo '<br>Cache disabled claring cache ...';
                        $query = "delete from {$this->wp_prefix}automatic_general where item_type='tw_{$camp->camp_id}_$keyword' ";
                        $this->db->query($query);

                        // reset index
                        $query = "update {$this->wp_prefix}automatic_keywords set keyword_start =1 where keyword_camp={$camp->camp_id}";
                        $this->db->query($query);

                        delete_post_meta($camp->camp_id, 'wp_instagram_next_max_id' . md5($keyword));

                    }

                    //if card OPT_TW_CARDS
                    if (in_array('OPT_TW_CARDS', $camp_opt) || stristr($camp->camp_post_content, 'item_embed')) {

                        $item_id = $temp['item_id'];

                        //getting card embed https://api.twitter.com/1/statuses/oembed.json?url=https://twitter.com/zzz/status/463440424141459456

                        echo '<br>Getting embed code from twitter...';

                        //curl get
                        $x = 'error';
                        $url = 'https://api.twitter.com/1/statuses/oembed.json?url=https://twitter.com/zzz/status/463440424141459456';
                        $url = wp_automatic_str_replace('463440424141459456', $item_id, $url);

                        curl_setopt($this->ch, CURLOPT_HTTPGET, 1);
                        curl_setopt($this->ch, CURLOPT_URL, wp_automatic_trim($url));

                        $exec = curl_exec($this->ch);
                        $x = curl_error($this->ch);

                        if (stristr($exec, 'widgets.js')) {

                            $json_embed = json_decode($exec);

                            $embed_html = $json_embed->html;

                            if (wp_automatic_trim($embed_html) != '') {

                                $temp['item_embed'] = $embed_html;

                                if (in_array('OPT_TW_CARDS', $camp_opt)) {
                                    $temp['item_description'] = $embed_html;
                                }

                            } else {
                                echo '<br>Can not extract embed html.';
                            }

                        } else {
                            echo '<br>Non expected embed reply.';
                        }

                    }

                    //Auto embed video
                    $temp['item_video_embed'] = '';
                    if (in_array('OPT_TW_VID_EMBED', $camp_opt) && !stristr(($camp->camp_post_content), 'item_video_url')) {

                        $vidEmbed = ''; //ini

                        if (in_array('OPT_TW_VID_EMBED_DIRECT', $camp_opt) && isset($temp['item_video_url_direct']) && wp_automatic_trim($temp['item_video_url_direct']) != '') {
                            $vidEmbed = "[embed]{$temp['item_video_url_direct']}[/embed]";
                        } elseif ( isset($temp['item_video_url']) && wp_automatic_trim($temp['item_video_url']) != '') {

                            $vidEmbed = '<blockquote class="twitter-video"><a href="' . $temp['item_video_url'] . '"></a></blockquote>
	<script async src="//platform.twitter.com/widgets.js" charset="utf-8"></script>
					';

                        }

                        $temp['item_video_embed'] = $vidEmbed;

                        $temp['item_description'] = $temp['item_description'] . $vidEmbed;
                    }

                    //Fix date timezone
                    $temp['item_created_at'] = get_date_from_gmt(gmdate('Y-m-d H:i:s', strtotime($temp['item_created_at'])));

                    //if there is a quoted tweet, embed it in the description with code [embed]{quoted_tweet_url}[/embed]
                    //quoted tweet exist if data includes referenced_tweets array and first element is the tweet and the tweet type is quoted
                    if (isset($item->data->referenced_tweets)) {
                        if ($item->data->referenced_tweets[0]->type == 'quoted') {
                            $quoted_tweet_id = $item->data->referenced_tweets[0]->id;
                            $quoted_tweet_url = 'https://twitter.com/' . $temp['item_author_screen_name'] . '/status/' . $quoted_tweet_id;

                            //report quoted tweet
                            echo '<br>Quoted tweet found:' . $quoted_tweet_url;

                            $temp['item_description'] = $temp['item_description'] . '<br><br>[embed]' . $quoted_tweet_url . '[/embed]';
                        }
                    }

                    //shared links replace with direct links
                    if (in_array('OPT_TW_EXPAND', $camp_opt)) {
                        if (isset($item->data->entities->urls)) {
                            foreach ($item->data->entities->urls as $single_url) {
                                $temp['item_description'] = wp_automatic_str_replace('href="' . $single_url->url . '"', 'href="' . $single_url->expanded_url . '"', $temp['item_description']);
                                $temp['item_description'] = wp_automatic_str_replace('>' . $single_url->url . '<', '>' . $single_url->display_url . '<', $temp['item_description']);
                            }
                        }
                    }

                    //original link
                    $original_post_url = isset($item->data->entities->urls[0]->expanded_url) ? $item->data->entities->urls[0]->expanded_url : '';
                    $temp['item_original_link'] = $original_post_url;

                    //external image from shared links
                    if (wp_automatic_trim($temp['item_image']) == '' && wp_automatic_trim($temp['item_original_link']) != '' && !stristr($temp['item_original_link'], 'twitter.com')) {
                        echo '<br>Extracting image from external link:' . $temp['item_original_link'];

                        //curl get
                        $x = 'error';
                        curl_setopt($this->ch, CURLOPT_HTTPGET, 1);
                        curl_setopt($this->ch, CURLOPT_URL, wp_automatic_trim($temp['item_original_link']));

                        if (stristr($temp['item_original_link'], 'bit.ly')) {
                            curl_setopt($this->ch, CURLOPT_ENCODING, 'gzip, deflate, br');
                        }

                        $exec = curl_exec($this->ch);
                        $x = curl_error($this->ch);

                        if (stristr($exec, 'twitter:image') || stristr($exec, 'og:image')) {
                            preg_match('{twitter:image" content="(.*?)"}', $exec, $imgMatchs);

                            if (isset($imgMatchs[1]) && wp_automatic_trim($imgMatchs[1]) == '') {
                                preg_match('{og:image" content="(.*?)"}', $exec, $imgMatchs);
                            }

                            if (isset($imgMatchs[1]) && wp_automatic_trim($imgMatchs[1]) != '') {
                                $temp['item_image'] = $imgMatchs[1];
                                $temp['item_description'] = '<img src="' . $imgMatchs[1] . '"/><br><br>' . $temp['item_description'];
                            }

                        }

                    }

                    return $temp;

                } else {

                    echo '<br>No links found for this keyword';
                }
            } // if trim
        } // foreach keyword
    }

    /**
     * Retrieves the access token for the Twitter API.
     *
     * This function is responsible for obtaining the access token required to authenticate
     * requests to the Twitter API. The access token is used to authorize the application
     * to interact with Twitter on behalf of the user.
     *
     * @return string The access token for the Twitter API.
     */
    public function get_access_token()
    {

        if (wp_automatic_trim($this->access_token) == '') {
            $this->access_token = wp_automatic_trim(get_option('wp_automatic_x_token', ''));
        }

        return $this->access_token;
    }

    /**
     * Retrieves the item ID from a Twitter post.
     *
     * This function is responsible for extracting and returning the unique
     * identifier of a specific item (e.g., tweet) from Twitter.
     *
     * @return string The unique identifier of the Twitter item.
     */
    public function get_item_id()
    {
        return $this->current_item->tweet_id;

    }

    /**
     * Retrieves the author ID from a Twitter post.
     *
     * This function is responsible for extracting and returning the unique
     * identifier of the author of a specific item (e.g., tweet) from Twitter.
     *
     * @return string The unique identifier of the Twitter item author.
     */
    public function get_item_author_id()
    {

        
        //if is set user info
        if(isset($this->current_item->user_info)){
            return $this->current_item->user_info->rest_id;
        }

        //if is set author
        if(isset($this->current_item->author)){
            return $this->current_item->author->rest_id;
        }
    }

    /**
     * Retrieves the URL of the current item.
     *
     * @return string The URL of the item.
     */
    public function get_item_url()
    {
        return 'https://x.com/' . $this->get_item_author_id() . '/status/' . $this->get_item_id();
    }

    //get_item_author_name
    public function get_item_author_name()
    {

        //if is set user info [search query]
        if (isset($this->current_item->user_info)) {
            return $this->current_item->user_info->name;
        }

        //if is set author
        if (isset($this->current_item->author)) {
            return $this->current_item->author->name;
        }
         
    }

    //get_item_author_screen_name example alexiskold
    public function get_item_author_screen_name()
    {
        //if is set user_info
        if (isset($this->current_item->user_info)) {
            return $this->current_item->user_info->screen_name;
        }

        //if is set author
        if (isset($this->current_item->author)) {
            return $this->current_item->author->screen_name;
        }
        
    }

    //get_item_author_description
    public function get_item_author_description()
    {
        //if is set user_info
        if (isset($this->current_item->user_info)) {
            return $this->current_item->user_info->description;
        }

        //if is set author
        if (isset($this->current_item->author)) {
            return $this->current_item->author->name;
        }
        
    }

    //get_item_author_url
    public function get_item_author_url()
    {
        return 'https://x.com/' . $this->get_item_author_screen_name();
    }

    //get_item_author_profile_image
    public function get_item_author_profile_image()
    {

        //ini
        $img = '';

        //if is set user info
        if(isset($this->current_item->user_info)){
            $img = $this->current_item->user_info->avatar;
        }

        //if is set author
        if(isset($this->current_item->author)){
            $img = $this->current_item->author->avatar;
        }
        

        return wp_automatic_str_replace('normal', '200x200', $img);

    }

}
