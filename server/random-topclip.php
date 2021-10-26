<?php
    $username = check_username( $_GET['username'] );
    $min_views = check_min_views( $_GET['views'] );
    $min_clips = 25;
    $sender = $_GET['sender'];
    if ( $username === "fuchs_" ) { $username = $sender; }
    $access_token = get_access_token();
    $user = get_user_id($username, $access_token);
    $filtered_games = ["Among Us"];
    $filtered_game_ids = get_filtered_game_ids($filtered_games, $access_token);
    $clips = get_clips($user, $access_token);
    list($filtered_clips, $rejected_clips) = get_filtered_clips($clips, $filtered_game_ids);
    list($random_clip, $debug) = get_random_clip($filtered_clips, $user, $min_views, $min_clips);

    /* example response
        [id] => StupidIntelligentHorseradishCharlieBitMe
        [url] => https://clips.twitch.tv/StupidIntelligentHorseradishCharlieBitMe
        [embed_url] => https://clips.twitch.tv/embed?clip=StupidIntelligentHorseradishCharlieBitMe
        [broadcaster_id] => 513203757
        [broadcaster_name] => kae_tv
        [creator_id] => 113199766
        [creator_name] => AaronsArcade
        [video_id] => 
        [game_id] => 516575
        [language] => en
        [title] => Kae Top Frags - "kick them when they are down"
        [view_count] => 79
        [created_at] => 2020-05-01T02:54:01Z
        [thumbnail_url] => https://clips-media-assets2.twitch.tv/AT-cm%7C692958241-preview-480x272.jpg
        [duration] => 4.9
    */
    
    // Start sending JSON response
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');

    echo json_encode(
        array( 
            "channel" => $random_clip->broadcaster_name, 
            "duration" => $random_clip->duration,
            "creator" => $random_clip->creator_name,
            "title" => $random_clip->title,
            "url" => str_replace( "-preview-480x272.jpg", ".mp4", $random_clip->thumbnail_url),
            "views" => $random_clip->view_count,
            "date" => $random_clip->created_at,
            "debug" => array(
                "total_unfiltered_clips" => count( $clips ),
                "total_filtered_clips" => count( $filtered_clips ),
                "min_views" =>  $min_views,
                "min_clips" => $min_clips,
                "last_index" => $debug['limit'],
                "random_index" => $debug['random_index'],
                "description" => $debug['mode'],
                "clip_url" => $random_clip->url,
                "filtered_game_ids" => join(", ", $filtered_game_ids),
                "sender" => $sender
            )
        )
    );
    exit;

    /*
        Functions
        -------------------------
        - check_username
        - check_min_views
        - get_access_token (Twitch API)
        - get_http_header
        - get_user_id (Twitch API)
        - get_filtered_game_ids (Twitch API)
        - get_clips (Twitch API)
        - get_random_clip
    */

    function check_username($username) {
        $username = explode(" ", $username);
        $username = $username[0];
        $username = str_replace("@", "", $username);

        if ( !$username ) {
            echo "No valid username.";
            exit();
        }

        return $username;
    }

    function check_min_views( $min_views ) {
        $min_views = intval( $min_views );

        if ( !$min_views || $min_views < 0 ) {
            $min_views = 10;
        }  

        return $min_views;
    }

    function get_access_token(){
        $cho = curl_init('https://id.twitch.tv/oauth2/token');
        curl_setopt($cho, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($cho, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($cho, CURLOPT_POST, 1);

        include("auth.php");
        $fields = array(
            'client_id' => $CLIENT_ID,
            'client_secret' => $CLIENT_SECRET,
            'grant_type' => 'client_credentials',
            'token_type' => 'bearer',
        );

        curl_setopt($cho, CURLOPT_POSTFIELDS, $fields);
        $output = curl_exec($cho);
        $oauth = json_decode($output, true);
        $token = $oauth['access_token'];
        return $token;
    }

    function get_http_header($access_token){
        include("auth.php");
        $headers = array(
            'Client-ID: ' . $CLIENT_ID,
            'Authorization: Bearer ' . $access_token,
        );
        return $headers;
    }

    function get_user_id($username, $access_token) {
        $curl = curl_init();
        $url = 'https://api.twitch.tv/helix/users?login=' . $username;
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, get_http_header($access_token));
        $output = curl_exec($curl);
        $json_result = json_decode($output);

        if ( count( $json_result->data) == 0 ) {
            echo "@" . $username . " is an invalid twitch username.";
            exit();
        }

        return $json_result->data[0];
    }

    function get_filtered_game_ids($games, $access_token) {
        $game_url = "name=";
        foreach ( $games as $game ) {
            $game_url .= urlencode($game) . "&name=";
        }
    
        $curl = curl_init();
        $url = 'https://api.twitch.tv/helix/games?' . $game_url;
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, get_http_header($access_token));
        $output = curl_exec($curl);
        $json_result = json_decode($output);
        $game_ids = array();
    
        if ( $json_result->data ) {
            foreach ( $json_result->data as $key => $game ) {
                array_push( $game_ids, $game->id );
            }
        }

        return $game_ids;
    }

    function get_clips($user, $access_token) {
        $ch = curl_init();
        $url = 'https://api.twitch.tv/helix/clips?broadcaster_id=' . $user->id . '&first=100';
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, get_http_header($access_token));
        $output = curl_exec($ch);
        curl_close ($ch);
        $json_result = json_decode($output);
        return $json_result->data;
    }

    function get_filtered_clips($clips, $game_ids){
        $filtered = array();
        $rejected = array();

        foreach( $clips as $key => $clip) {

            // if clip has the game id of filtered games, remove it
            if ( in_array( $clip->game_id, $game_ids ) ) {
                array_push( $rejected, $clip );
                continue;
            } 

            // if kae clip is VALORANT
            // if ( $clip->broadcaster_name === "kae_tv" && $clip->game_id === "516575" ) {
            //     array_push( $rejected, $clip );
            //     continue;
            // }

            array_push( $filtered, $clip );
        }

        return array( $filtered, $rejected );
    }

    function get_random_clip($clips, $user, $min_views, $min_clips){
        $count = count( $clips );
        $limit = $count - 1;
        $mode = 'all clips';
        
        if ( $count == 0 ) {
            echo "@" . $user->display_name . " has no clips on their channel PepeHands";
            exit();
        }

        foreach( $clips as $key => $clip) {
            if ( $clip->view_count < $min_views ) {
                // if streamer has less than 10 clips with min views => ignore min views
                if ( $key < $min_clips ) {
                    $mode = 'only ' . $key . ' clips with at least ' . $min_views . ' views => all clips';
                    break;
                }

                // ignore clips with less than min views
                if ( $key - 1 >= 0 ) {
                    $mode = $key . ' clips with at least ' . $min_views . ' views found => limited clips';
                    $limit = $key - 1;
                }
                break;
            }
        }

        // GET RANDOM CLIP
        $random_index = rand(0, $limit);
        $result = $clips[$random_index];
        return array(
            $result,
            array(
                'mode' => $mode,
                'random_index' => $random_index,
                'limit' => $limit,
            )
        );
    }
?>