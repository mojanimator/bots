<?php
$TOKEN = 'BOT TOKEN';
function request_url($method)
{
    global $TOKEN;
    return "https://api.telegram.org/bot" . $TOKEN . "/". $method;
}
function get_updates($offset)
{
    $url = request_url("getUpdates")."?offset=".$offset;
    $resp = file_get_contents($url);
    $result = json_decode($resp, true);
    if ($result["ok"] == true) {
        return $result["result"];
    }
    return array();
}
function send_reply($chatid, $msgid, $text)
{
    $data = array(
        'chat_id' => $chatid,
        'text'  => $text,
        'reply_to_message_id' => $msgid
    );
    // use key 'http' even if you send the request to https://...
    $options = array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ),
    );
    $context  = stream_context_create($options);
    $result = file_get_contents(request_url('sendMessage'), false, $context);
    print_r($result);
}
function sendPhoto($chatid, $urlphoto, $caption)
{
    $url = request_url('sendPhoto');
    $content = array( 'chat_id' => $chatid, 'photo' => new CURLFile(realpath($urlphoto)), 'caption' => $caption);
    print_r($content);
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    //curl_setopt($curl, CURLOPT_HTTPHEADER,array("Content-type: application/json"));
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    $json_response = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if ($status != 201) {
        die("Error: call to URL $url failed with status $status, response $json_response, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl));
    }
    curl_close($curl);
    $response = json_decode($json_response, true);
    return $response;
}
function create_response($text)
{
    return "definisi " . $text;
}
function process_message($message)
{
    $updateid = $message["update_id"];
    $message_data = $message["message"];
    if (isset($message_data["text"])) {
        $chatid = $message_data["chat"]["id"];
        $message_id = $message_data["message_id"];
        $text = $message_data["text"];
        $response = create_response($text);
        send_reply($chatid, $message_id, $response);
    }
    return $updateid;
}
function process_one()
{
    $update_id  = 0;
    if (file_exists("last_update_id")) {
        $update_id = (int)file_get_contents("last_update_id");
    }
    $updates = get_updates($update_id);
    foreach ($updates as $message) {
        $update_id = process_message($message);
    }
    file_put_contents("last_update_id", $update_id + 1);
}
function searchInMessage($keyword, $urlPhoto, $caption)
{
    $updates = get_updates(0);
    foreach ($updates as $message) {
        $message_data = $message["message"];
        if ($message_data['text'] == $keyword) {
            echo 'chat_id : '.$message_data['chat']['id'].'<br>';
            echo 'from : '.$message_data['from']['username'].'<br>';
            echo 'isi : '.$message_data['text'].'<br>';
            sendPhoto($message_data['chat']['id'], $urlPhoto, $caption);
        }
    }
}
searchInMessage('Linephoto', 'testphoto.jpg', 'Ini Udah bisa');
//while (true) {
    //process_one();
//}

//echo get_updates(0);
