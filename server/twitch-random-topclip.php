<?php

    $username = $_GET['username'];
    $username = explode(" ", $username);
    $username = $username[0];
    $username = str_replace("@", "", $username);
    $min_views = intval($_GET['views']);

    if ( !$min_views > 0 ) {
        $min_views = 10;
    }  

    if ( !$username ) {
        echo "No valid username.";
        exit();
    }
        
    // GET ACCESS TOKEN
    $cho = curl_init('https://id.twitch.tv/oauth2/token');
    curl_setopt($cho, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($cho, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($cho, CURLOPT_POST, 1);

    include_once("auth.php");
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

    // PREPARE HEADERS
    $headers = array(
        'Client-ID: ' . $fields['client_id'],
        'Authorization: Bearer ' . $token,
    );

    // GET USER ID (V2)
    $curl = curl_init();
    $url = 'https://api.twitch.tv/helix/users?login=' . $username;
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    $output = curl_exec($curl);
    $json_result = json_decode($output);

    if ( count( $json_result->data) == 0 ) {
        echo "@" . $username . " is an invalid twitch username.";
        exit();
    }

    $result = $json_result->data[0];
    $user_id = $result->id;
    $display_name = $result->display_name;

    // GET GAME IDS
    $games = ["Among Us"];

    $game_url = "name=";
    foreach ( $games as $game ) {
        $game_url .= urlencode($game) . "&name=";
    }

    $curl = curl_init();
    $url = 'https://api.twitch.tv/helix/games?' . $game_url;
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    $output = curl_exec($curl);
    $json_result = json_decode($output);
    $game_ids = array();

    if ( $json_result->data ) {
        foreach ( $json_result->data as $key => $game ) {
            array_push( $game_ids, $game->id );
        }
    }

    // GET CLIPS
    $ch = curl_init();
    $url = 'https://api.twitch.tv/helix/clips?broadcaster_id=' . $user_id . '&first=100';
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $output = curl_exec($ch);
    curl_close ($ch);
    $json_result = json_decode($output);

    // FILTER CLIPS
    $filtered = array();
    $rejected = array();

    foreach( $json_result->data as $key => $clip) {

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
    
    // CHECK MIN VIEWS OF ALL CLIPS
    $count = count( $filtered );
    $limit = $count - 1;
    $mode = 'all clips';
    
    if ( $count == 0 ) {
        echo "@" . $display_name . " has no clips on their channel PepeHands";
        exit();
    }


    foreach( $filtered as $key => $clip) {
        if ( $clip->view_count < $min_views ) {
            // if streamer has less than 10 clips with min views => ignore min views
            if ( $key < 10 ) {
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
    $result = $filtered[$random_index];

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
            "channel" => $result->broadcaster_name, 
            "duration" => $result->duration,
            "creator" => $result->creator_name,
            "title" => $result->title,
            "url" => str_replace( "-preview-480x272.jpg", ".mp4", $result->thumbnail_url),
            "views" => $result->view_count,
            "date" => $result->created_at,
            "debug" => array(
                "total_unfiltered_clips" => count( $json_result->data ),
                "total_filtered_clips" => $count,
                "min_views" =>  $min_views,
                "last_index" => $limit,
                "random_index" => $random_index,
                "description" => $mode,
                "clip_url" => $result->url,
                "filtered_game_ids" => join(", ", $game_ids) 
            )
        )
    );
    exit;
?>