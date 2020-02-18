<?php


function sending_messages(
    $accessToken,
    $replyToken,
    $response_format_text
) {
        $post_data = [
"replyToken" => $replyToken,
"messages" => [$response_format_text]
];

    $ch = curl_init("https://api.line.me/v2/bot/message/reply");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
'Content-Type: application/json; charser=UTF-8',
'Authorization: Bearer ' . $accessToken
));
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

function get_content($id,$requestToken){

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $requestToken));
    $url = "https://api.line.me/v2/bot/message/".$id."/content";
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

$set = file_get_contents('./api/key.json');
$set = (array)json_decode($set, true);


$project_file = $_SERVER['HTTP_HOST'];
$client_id = $set["client_id"];
$client_secret = $set["client_secret"];
$accessToken = $set["accessToken"];
$apikey = $set["apikey"];

$json_string = file_get_contents('php://input');
$json_object = json_decode($json_string);


$http_php_input_num = 0;
while ($json_object->{"events"}[$http_php_input_num] != null) {
$replyToken = $json_object->{"events"}[$http_php_input_num]->{"replyToken"}; 
$message_id = $json_object->{"events"}[$http_php_input_num]->{"message"}->{"id"}; 
$message_type = $json_object->{"events"}[$http_php_input_num]->{"message"}->{"type"};


$accessToken = $set["accessToken"];

    if ($set['tokentime'] <= time()) {
        $postData = array(
'grant_type'                    => 'client_credentials',
'client_id'                     => $client_id,
'client_secret' => $client_secret
);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_URL, 'https://api.line.me/v2/oauth/accessToken');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($response);
        $requestToken = $json->access_token;
        $set['tokentime'] = time() + 2592000;
        $set['requesttoken'] = $requestToken;
        $set_str = json_encode($set, (JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        file_put_contents('./api/key.json', $set_str, LOCK_EX);
    } else {
        $requestToken = $set['requesttoken'];
    }

if($message_type == "file" or $message_type == "audio"){
    
    $directory_path = "audio";
    if (file_exists($directory_path)) {
    } else {
        mkdir($directory_path, 0777);
    }
    $file = get_content($message_id,$requestToken);
    $num = time();
    $url = $project_file.'audio/audio'.$num;
    file_put_contents('./audio/audio'.$num, $file);
    $data = json_decode(file_get_contents('http://api.rest7.com/v1/sound_convert.php?url=' . $url . '&format=wav'));
    if (@$data->success !== 1) {
        die('Failed');
    }
    $file = file_get_contents($data->file);

    $jsonArray = array();
    $jsonArray["config"]["encoding"] = "LINEAR16";
    $jsonArray["config"]["languageCode"] = "ja-JP";
    $jsonArray["config"]["enableWordTimeOffsets"] = false;
        
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, "https://speech.googleapis.com/v1/speech:recognize?key=".$apikey);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HEADER, true);
        
    $jsonArray["audio"]["content"] = base64_encode($file);
    if ($jsonArray["audio"]["content"] != null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($jsonArray));
        $response = curl_exec($curl);
        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        $result = json_decode($body, true);
        if ($result["results"][0]["alternatives"][0]["transcript"] != null) {
            $pa = $result["results"][0]["alternatives"][0]["confidence"] * 100;
            $response_format_text = [
                "type" => text,
                "text" => $result["results"][0]["alternatives"][0]["transcript"]."\n適合率".$pa.'%'
                ];
                
    sending_messages($accessToken, $replyToken, $response_format_text);
        } else {
            $response_format_text = [
        "type" => text,
        "text" => "変換エラーです".$response
        ];
        }
        
    sending_messages($accessToken, $replyToken, $response_format_text);
    }else{
        
        $response_format_text = [
            "type" => text,
            "text" => "変換エラーです"
            ];
            
    sending_messages($accessToken, $replyToken, $response_format_text);
    }
}
$http_php_input_num++;
}