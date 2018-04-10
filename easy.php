<?php
define('BOT_TOKEN', '474683157:AAHJbS5OmIAzUcTC67SeN06YzSWBCWrqC04');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('ADMIN_ID', 283838620);
define('C_VOTE_RESULTS', 1);
define('C_ALL_USERS', -1);
define('C_ALL_VOTERS_TO_BOT', -2);

global $debug;
$debug = true;


function apiRequestWebhook($method, $parameters)
{
    if (!is_string($method)) {
        error_log("Method name must be a string\n");
        return false;
    }
    if (!$parameters) {
        $parameters = array();
    } elseif (!is_array($parameters)) {
        error_log("Parameters must be an array\n");
        return false;
    }
    $parameters["method"] = $method;
    header("Content-Type: application/json");
    echo json_encode($parameters);
    return true;
}

function exec_curl_request($handle)
{
    $response = curl_exec($handle);
    if ($response === false) {
        $errno = curl_errno($handle);
        $error = curl_error($handle);
        error_log("Curl returned error $errno: $error\n");
        curl_close($handle);
        return false;
    }
    $http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
    curl_close($handle);
    if ($http_code >= 500) {
        // do not wat to DDOS server if something goes wrong
        sleep(10);
        return false;
    } elseif ($http_code != 200) {
        $response = json_decode($response, true);
        error_log("Request has failed with error {$response['error_code']}: {$response['description']}\n");
        if ($http_code == 401) {
            throw new Exception('Invalid access token provided');
        }
        return false;
    } else {
        $response = json_decode($response, true);
        if (isset($response['description'])) {
            error_log("Request was successfull: {$response['description']}\n");
        }
        $response = $response['result'];
    }
    return $response;
}

function apiRequest($method, $parameters)
{
    if (!is_string($method)) {
        error_log("Method name must be a string\n");
        return false;
    }
    if (!$parameters) {
        $parameters = array();
    } elseif (!is_array($parameters)) {
        error_log("Parameters must be an array\n");
        return false;
    }
    if (isset($parameters['caption']) and $parameters['caption'] != 'کرمان' and $parameters['caption'] != 'ماهان - پنجشنبه ها')
        foreach ($parameters as $key => &$val) {
            // encoding to JSON array parameters, for example reply_markup
            if (!is_numeric($val) && !is_string($val)) {
                $val = json_encode($val);
            }
        }
    $url = API_URL . $method . '?' . http_build_query($parameters);
    //$url = API_URL . $method;
    $handle = curl_init($url);
    curl_setopt($handle, CURLOPT_POST, true);
    curl_setopt($handle, CURLOPT_HEADER, false);
    curl_setopt($handle, CURLOPT_POSTFIELDS, $parameters);
    curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($handle, CURLOPT_TIMEOUT, 60);
    return exec_curl_request($handle);

}

function apiRequestJson($method, $parameters)
{
    if (!is_string($method)) {
        error_log("Method name must be a string\n");
        return false;
    }
    if (!$parameters) {
        $parameters = array();
    } elseif (!is_array($parameters)) {
        error_log("Parameters must be an array\n");
        return false;
    }
    $parameters["method"] = $method;

    $handle = curl_init(API_URL . $method);

    curl_setopt($handle, CURLOPT_POST, true);
    curl_setopt($handle, CURLOPT_HEADER, false);
    curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($handle, CURLOPT_TIMEOUT, 60);
    curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
    curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
    return exec_curl_request($handle);
}

function connectToDatabase()
{
    //   -2 > null database
    //   -1 > exception
    $dbo = null;
    $user = 'mojraj_bots';
    $password = '4$2+euHy$snJ';
    $dbname = 'mojraj_bots';

//    $user = 'root';
//    $password = '5564351';
//    $dbname = 'bots';

    $connectionString = 'mysql:host=' . 'localhost' . ';dbname=' . $dbname . ';charset=utf8mb4';
    try {
        $dbo = new PDO($connectionString, $user, $password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING));
        if ($dbo === null) {
            return -2;
        }
        return $dbo;
    } catch (PDOException $e) {
        echo $e->getMessage();
        return -1;
    }
}

function saveToDataBase($table, $user_id, $user_name, $first_name, $last_name, $message_date, $response)
{
    $dbo = connectToDatabase();
    if ($dbo === -1 || $dbo === -2) {
        return;
    }

    $dbo->exec("REPLACE  INTO  `$table`  VALUES ('$user_id','$user_name','$first_name','$last_name','$message_date','$response' )");
    //$stmt = $dbo->query("SELECT * FROM '$table' ");
    //$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    //$stmt->closeCursor();
    //return $results;
}

function getFromDataBase($user_id, $command)
{

    $dbo = connectToDatabase();
    if ($dbo === -1 || $dbo === -2) {
        return;
    }
//    if ($user_id != ADMIN_ID) {
//        $results = "شما مجاز به دسترسی به این بخش نیستید !";
//        return $results;
//    }

    if ($command == C_VOTE_RESULTS)
        $stmt = $dbo->query("SELECT count(response) / (select count(*) FROM kgut_bot) as percent,response FROM kgut_bot group by response");

    elseif ($command == C_ALL_USERS)
        $stmt = $dbo->query("SELECT user_id FROM kgut_bot");

    elseif ($command == C_ALL_VOTERS_TO_BOT)
        $stmt = $dbo->query("SELECT user_id FROM 'inline_test' ");


    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    return $results;
}

function sendToUsers()
{

}

function processMessage($update)
{
    global $msgID;
    global $imgID;
    global $vidID;
    global $docID;

    global $sending_message;
    global $sending_image;
    global $sending_video;
    global $sending_document;

    global $substring_offset;
    $substring_offset = -1;  // -2 for test in pc | -1 for server


//this is for message file
    $myfile = fopen("message_info.txt", "r") or die("Unable to open file!");
    $msgID = fgets($myfile);
    $sending_message = fgets($myfile);
    fclose($myfile);

//this is for image file
    $myfile = fopen("image_info.txt", "r") or die("Unable to open file!");
    $imgID = fgets($myfile);
    $sending_image = fgets($myfile);
    fclose($myfile);

//this is for video file
    $myfile = fopen("video_info.txt", "r") or die("Unable to open file!");
    $vidID = fgets($myfile);
    $sending_video = fgets($myfile);
    fclose($myfile);

//this is for document file
    $myfile = fopen("document_info.txt", "r") or die("Unable to open file!");
    $docID = fgets($myfile);
    $sending_document = fgets($myfile);
    fclose($myfile);


//emoji
    $e_happy = "\xF0\x9F\x98\x81";
    $e_sad = "\xF0\x9F\x98\x92";
    $e_bus = "\xF0\x9F\x9A\x8C";
    $e_results = "\xF0\x9F\x93\x8A";
    $e_vote = "\xF0\x9F\x8E\xAD";
//    $e_uni_address = "\xF0\x9F\x93\xAC";
    $e_uni_mailbox = "\xF0\x9F\x93\xAB";
    $e_uni_address = "\xF0\x9F\x93\x8C";
    $e_uni_fax = "\xF0\x9F\x93\xA0";
    $e_uni_email = "\xF0\x9F\x93\xA7";
    $e_uni_post = "\xF0\x9F\x93\xAE";
    $e_send_message = "\xE2\x9C\x8F";
    $e_send_image = "\xF0\x9F\x8C\x84";
    $e_send_video = "\xF0\x9F\x8E\xAC";
    $e_send_document = "\xF0\x9F\x92\xBC";
    $e_yes = "\xE2\x9C\x85";
    $e_no = "\xE2\x9B\x94";
    $e_comments = "\xF0\x9F\x93\x9D";
    $e_food = "\xF0\x9F\x8D\x96";

    //persian words

    $F_VOTE = 'نظر سنجی دانشگاه' . $e_vote;
    $F_SERVICE_TIMES = 'ساعت حرکت سرویس ها' . $e_bus;
    $F_COMMENTS = 'انتقادات و پیشنهادات' . $e_comments;
    $F_SALAM = 'سلام';

    $F_BALE = 'بله';
    $F_KHEIR = 'خیر';
    $F_ERSAL_SHAVAD = 'ارسال شود';
    $F_ERSAL_NASHAVAD = 'ارسال نشود';
    $F_RESULTS = 'نتایج نظر سنجی' . $e_results;
    $F_UNI_ADDRESS = 'آدرس دانشگاه' . $e_uni_address;
    $F_UNI_ADDRESS_DETAIL = $e_uni_address . 'کرمان - انتهای اتوبان هفت باغ علوی ';
    $F_UNI_FAX = $e_uni_fax . 'نمابر: ' . "03433776617";
    $F_UNI_POSTCODE = $e_uni_post . 'کد پستی: ' . "7631818356";
    $F_UNI_MAIL = $e_uni_email . 'ایمیل: ' . " info@kgut.ac.ir";
    $F_SEND_MESSAGE = "ارسال پیام به اعضا" . $e_send_message;
    $F_SEND_IMAGE = "ارسال تصویر به اعضا" . $e_send_image;
    $F_SEND_VIDEO = "ارسال ویدیو به اعضا" . $e_send_video;
    $F_SEND_DOCUMENT = "ارسال  سند به اعضا" . $e_send_document;
    $F_SEND_INLINE_VOTE = 'نظر سنجی بات' . $e_vote;
    $F_FOOD = "سامانه تغذیه" . $e_food;

    // keyboards
    $main_keyboard = array(array($F_SERVICE_TIMES), array($F_VOTE), array($F_UNI_ADDRESS), array($F_FOOD), array($F_COMMENTS), array($F_RESULTS));
    $admin_keyboard = array(array($F_SERVICE_TIMES), array($F_VOTE), array($F_UNI_ADDRESS),
        array($F_FOOD), array($F_COMMENTS), array($F_RESULTS), array($F_SEND_MESSAGE), array($F_SEND_IMAGE), array($F_SEND_VIDEO), array($F_SEND_DOCUMENT),
        array($F_SEND_INLINE_VOTE));


// this is for callback inline buttons

    if (isset($update['callback_query'])) {


        $userID = $update['callback_query']['from']['id'];
        $userName = $update['callback_query']['from']['username'];
        $first_name = $update['callback_query']['from']['first_name'];
        $last_name = $update['callback_query']['from']['last_name'];
        $message_date = $update['callback_query']['message']['date'];
        $chat_id = $update['callback_query']['message']['chat']['id'];

        //answer to inline key for remove circle

        $resp = 'با موفقیت ثبت شد';
        apiRequestJson("answerCallbackQuery", array('callback_query_id' => $update['callback_query']['id'], "text" => $resp));


        if ($userID === ADMIN_ID)
            $keyboard = $admin_keyboard;
        else
            $keyboard = $main_keyboard;

        $text = $update['callback_query']['data'];


        if ($text === "inline_yes") {

            saveToDataBase('inline_test', $userID, $userName, $first_name, $last_name, $message_date, 'راضی');
            $resp = 'ممنون' . ' ' . $first_name . ' ' . $last_name . ' !';
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => $resp, 'reply_markup' => array(
                'keyboard' => $keyboard,
                'one_time_keyboard' => true,
                'resize_keyboard' => true)));


        } elseif ($text === "inline_no") {

            saveToDataBase('inline_test', $userID, $userName, $first_name, $last_name, $message_date, 'نا راضی');
            $resp = 'ممنون' . ' ' . $first_name . ' ' . $last_name . ' !';
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => $resp, 'reply_markup' => array(
                'keyboard' => $keyboard,
                'one_time_keyboard' => true,
                'resize_keyboard' => true)));


        }
        return;
    }


    $message = $update['message'];


    // process incoming message

    $message_id = $message['message_id'];
    if (isset($message['photo'])) {
        $image_id = $message['photo'][count($message['photo']) - 1]["file_id"];
        $caption = $message['caption'];
    } elseif (isset($message['video'])) {
        $video_id = $message['video']["file_id"];
        $caption = $message['caption'];
    } elseif (isset($message['document'])) {
        $document_id = $message['document']["file_id"];
        $caption = $message['caption'];
    } else {
        $image_id = "";
        $video_id = "";
        $document_id = "";
        $caption = "";
    }
    $chat_id = $message['chat']['id'];
    $userName = $message["from"]["username"];
    $userID = $message["from"]["id"];
    $first_name = $message["chat"]["first_name"];
    $last_name = $message["chat"]["last_name"];
    $message_date = $message['date'];

    if ($userID === ADMIN_ID)
        $keyboard = $admin_keyboard;
    else
        $keyboard = $main_keyboard;

    if (isset($message/*['text']*/)) {
        // incoming text message
        if (isset($message["text"])) {
            $text = $message['text'];
        } else {
            $text = "_";
        }


        if (strpos($text, "/start") === 0) {
            $resp = $F_SALAM . '  ' . $first_name . ' ' . $last_name . PHP_EOL;

            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => $resp, 'reply_markup' => array(
                'keyboard' => $keyboard,
                'one_time_keyboard' => true,
                'resize_keyboard' => true)));


        } elseif (strpos($text, $F_SERVICE_TIMES) === 0) {

            $method = 'sendPhoto';
            $filename1 = realpath('assets/services_1.jpg');
            $filename2 = realpath('assets/services_2.jpg');

            $cfile = new CURLFile($filename1);
            $params = array(
                'chat_id' => $chat_id,
                'photo' => $cfile,
                'caption' => 'کرمان',


            );

            apiRequest("sendPhoto", $params);
            $params['photo'] = new CURLFile($filename2);
            $params['caption'] = 'ماهان - پنجشنبه ها';
            apiRequest("sendPhoto", $params);


        } elseif (strpos($text, $F_VOTE) === 0 or strpos($text, '/vote') === 0) {

//            $resp = $first_name . ' ' . $last_name . PHP_EOL;
            $resp = 'از وضعیت دانشگاه راضی هستید؟' . PHP_EOL;
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => $resp, 'reply_markup' => array(
                'keyboard' => array(array($F_BALE . $e_happy), array($F_KHEIR . $e_sad)),
                'one_time_keyboard' => true,
                'resize_keyboard' => true)));


        } elseif ($text === $F_BALE . $e_happy) {

            saveToDataBase('kgut_bot', $userID, $userName, $first_name, $last_name, $message_date, $F_BALE);
            $resp = 'ممنون' . ' ' . $first_name . ' ' . $last_name . ' !';
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => $resp, 'reply_markup' => array(
                'keyboard' => $keyboard,
                'one_time_keyboard' => true,
                'resize_keyboard' => true)));


        } elseif ($text === $F_KHEIR . $e_sad) {

            saveToDataBase('kgut_bot', $userID, $userName, $first_name, $last_name, $message_date, $F_KHEIR);
            $resp = 'ممنون' . ' ' . $first_name . ' ' . $last_name . ' !';
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => $resp, 'reply_markup' => array(
                'keyboard' => $keyboard,
                'one_time_keyboard' => true,
                'resize_keyboard' => true)));


        } elseif ($text === $F_SEND_INLINE_VOTE) {

            $membersID = getFromDataBase($userID, C_ALL_USERS);


            $keyboard = ['inline_keyboard' => [[
                ['text' => $F_BALE . "\xF0\x9F\x91\x8D", 'callback_data' => 'inline_yes'], ['text' => $F_KHEIR . "\xF0\x9F\x91\x8E", 'callback_data' => 'inline_no']
            ]]
            ];
            $resp = 'از مطالب بات راضی هستید؟';

            foreach ($membersID as $id)
                apiRequestJson("sendMessage", ['chat_id' => $id['user_id'], "text" => $resp, "from" => "@kgut_bot", 'reply_markup' => json_encode($keyboard)]);


        } elseif ($text === $F_RESULTS) {

            $tmp = getFromDataBase($userID, C_VOTE_RESULTS);
            $resp = 'از وضعیت دانشگاه راضی هستید؟' . PHP_EOL;
            $resp .= $tmp[0]['percent'] . '%' . "  " . $tmp[0]['response'] . PHP_EOL
                . $tmp[1]['percent'] . '%' . "  " . $tmp[1]['response'] . PHP_EOL;
            $resp .= "__________" . PHP_EOL;
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => $resp, 'reply_markup' => array(
                'keyboard' => $keyboard,
                'one_time_keyboard' => true,
                'resize_keyboard' => true)));


        } elseif ($text === $F_SEND_MESSAGE) {

            $myfile = fopen("message_info.txt", "w") or die("Unable to open file!");
            fwrite($myfile, "0" . PHP_EOL);//message id
            fwrite($myfile, "t"); //is sending file flag
            fclose($myfile);

            $resp = "پیام خود را بنویسید" . PHP_EOL;
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => $resp));


        } elseif ($text === $F_SEND_IMAGE) {

            $myfile = fopen("image_info.txt", "w") or die("Unable to open file!");
            fwrite($myfile, "0" . PHP_EOL);//image id
            fwrite($myfile, "t"); //is sending image flag
            fclose($myfile);

            $resp = "عکس را وارد کنید" . PHP_EOL;
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => $resp));


        } elseif ($text === $F_SEND_VIDEO) {

            $myfile = fopen("video_info.txt", "w") or die("Unable to open file!");
            fwrite($myfile, "0" . PHP_EOL);//video id
            fwrite($myfile, "t"); //is sending video flag
            fclose($myfile);

            $resp = "ویدیو را وارد کنید" . PHP_EOL;
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => $resp));


        } elseif ($text === $F_SEND_DOCUMENT) {

            $myfile = fopen("document_info.txt", "w") or die("Unable to open file!");
            fwrite($myfile, "0" . PHP_EOL);//video id
            fwrite($myfile, "t"); //is sending video flag
            fclose($myfile);

            $resp = "سند را وارد کنید" . PHP_EOL;
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => $resp));


        } elseif ($text === $F_ERSAL_SHAVAD . $e_yes) {

            $membersID = getFromDataBase($userID, C_ALL_USERS);

            if ($sending_message === "t") {

                $myfile = fopen("message.txt", "r") or die("Unable to open file!");
                $content = fread($myfile, filesize('message.txt'));
                fclose($myfile);

                foreach ($membersID as $id)
                    apiRequestJson("sendMessage", array('chat_id' => $id['user_id'], 'text' => $content, "from" => '@kgut_bot', "message_id" => $message_id));

                apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => "پیام با موفقیت ارسال شد !", 'reply_markup' => array(
                    'keyboard' => $keyboard,
                    'one_time_keyboard' => true,
                    'resize_keyboard' => true)));

                $myfile = fopen("message_info.txt", "w") or die("Unable to open file!");
                fwrite($myfile, "0" . PHP_EOL);  //message id
                fwrite($myfile, "f"); //is sending file flag
                fclose($myfile);


            } elseif ($sending_image === "t") {

                $myfile = fopen("image_caption.txt", "r") or die("Unable to open file!");
                $imgCaption = fread($myfile, filesize('image_caption.txt'));
                fclose($myfile);

                $params = array(
                    'chat_id' => null,
                    'photo' => substr($imgID, 0, $substring_offset),
                    "from" => "@kgut_bot",
                    'caption' => $imgCaption . '@kgut_bot',
                    'reply_markup' => array(
                        'keyboard' => $keyboard,
                        'one_time_keyboard' => true,
                        'resize_keyboard' => true)

                );

                foreach ($membersID as $id) {
                    $params['chat_id'] = $id['user_id'];

                    apiRequest("sendPhoto", $params);
                }

                apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => "عکس با موفقیت ارسال شد !", 'reply_markup' => array(
                    'keyboard' => $keyboard,
                    'one_time_keyboard' => true,
                    'resize_keyboard' => true)));

                $myfile = fopen("image_info.txt", "w") or die("Unable to open file!");
                fwrite($myfile, "0" . PHP_EOL);  //message id
                fwrite($myfile, "f"); //is sending file flag
                fclose($myfile);

            } elseif ($sending_video === "t") {

                $myfile = fopen("caption.txt", "r") or die("Unable to open file!");
                $vidCaption = fread($myfile, filesize('caption.txt'));
                fclose($myfile);

                $params = array(
                    'chat_id' => null,
                    'video' => substr($vidID, 0, $substring_offset),
                    "from" => "@kgut_bot",
                    'caption' => $vidCaption . '@kgut_bot',
                    'reply_markup' => array(
                        'keyboard' => $keyboard,
                        'one_time_keyboard' => true,
                        'resize_keyboard' => true)

                );

                foreach ($membersID as $id) {
                    $params['chat_id'] = $id['user_id'];

                    apiRequest("sendVideo", $params);
                }

                apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => "ویدیو با موفقیت ارسال شد !", 'reply_markup' => array(
                    'keyboard' => $keyboard,
                    'one_time_keyboard' => true,
                    'resize_keyboard' => true)));

                $myfile = fopen("video_info.txt", "w") or die("Unable to open file!");
                fwrite($myfile, "0" . PHP_EOL);  //message id
                fwrite($myfile, "f"); //is sending file flag
                fclose($myfile);

            } elseif ($sending_document === "t") {

                $myfile = fopen("caption.txt", "r") or die("Unable to open file!");
                $docCaption = fread($myfile, filesize('caption.txt'));
                fclose($myfile);

                $params = array(
                    'chat_id' => null,
                    'document' => substr($docID, 0, $substring_offset),
                    "from" => "@kgut_bot",
                    'caption' => $docCaption . '@kgut_bot',
                    'reply_markup' => array(
                        'keyboard' => $keyboard,
                        'one_time_keyboard' => true,
                        'resize_keyboard' => true)

                );

                foreach ($membersID as $id) {
                    $params['chat_id'] = $id['user_id'];

                    apiRequest("sendDocument", $params);
                }

                apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => "ویدیو با موفقیت ارسال شد !", 'reply_markup' => array(
                    'keyboard' => $keyboard,
                    'one_time_keyboard' => true,
                    'resize_keyboard' => true)));

                $myfile = fopen("document_info.txt", "w") or die("Unable to open file!");
                fwrite($myfile, "0" . PHP_EOL);  //document id
                fwrite($myfile, "f"); //is sending file flag
                fclose($myfile);

            }


        } elseif ($text === $F_ERSAL_NASHAVAD . $e_no) {

            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => "ارسال کنسل شد", 'reply_markup' => array(
                'keyboard' => $keyboard,
                'one_time_keyboard' => true,
                'resize_keyboard' => true)));

            $myfile = fopen("message_info.txt", "w") or die("Unable to open file!");
            fwrite($myfile, $msgID);  //message id
            fwrite($myfile, "f"); //is sending file flag
            fclose($myfile);

            $myfile = fopen("image_info.txt", "w") or die("Unable to open file!");
            fwrite($myfile, $imgID);  //image id
            fwrite($myfile, "f"); //is sending file flag
            fclose($myfile);

            $myfile = fopen("video_info.txt", "w") or die("Unable to open file!");
            fwrite($myfile, $vidID);  //video id
            fwrite($myfile, "f"); //is sending file flag
            fclose($myfile);

            $myfile = fopen("document_info.txt", "w") or die("Unable to open file!");
            fwrite($myfile, $docID);  //document id
            fwrite($myfile, "f"); //is sending file flag
            fclose($myfile);


        } elseif ($sending_message === "t") {

            $myfile = fopen("message_info.txt", "w") or die("Unable to open file!");
            fwrite($myfile, $message_id . PHP_EOL); //message id
            fwrite($myfile, "t"); //is sending file flag
            fclose($myfile);

            $myfile = fopen("message.txt", "w") or die("Unable to open file!");
            fwrite($myfile, $text . PHP_EOL . "@kgut_bot"); //message id
            fclose($myfile);

//            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => $text));

            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => $text . PHP_EOL . "@kgut_bot", 'reply_markup' => array(
                'keyboard' => array(array($F_ERSAL_SHAVAD . $e_yes), array($F_ERSAL_NASHAVAD . $e_no)),
                'one_time_keyboard' => true,
                'resize_keyboard' => true)));


        } elseif ($sending_image === "t") {

            $myfile = fopen("image_info.txt", "w") or die("Unable to open file!");
            fwrite($myfile, $image_id . PHP_EOL); //image id
            fwrite($myfile, "t"); //is sending file flag
            fclose($myfile);

            $myfile = fopen("image_caption.txt", "w") or die("Unable to open file!");
            fwrite($myfile, $caption . PHP_EOL); //message id
            fclose($myfile);

            $params = array(
                'chat_id' => $chat_id,
                'photo' => $image_id,
                'caption' => $caption . PHP_EOL . '@kgut_bot',
                "from" => '@kgut_bot',


                'reply_markup' => array(
                    'keyboard' => array(array($F_ERSAL_SHAVAD . $e_yes), array($F_ERSAL_NASHAVAD . $e_no)),
                    'one_time_keyboard' => true,
                    'resize_keyboard' => true)
            );

            apiRequest("sendPhoto", $params);


        } elseif ($sending_video === "t") {

            $myfile = fopen("video_info.txt", "w") or die("Unable to open file!");
            fwrite($myfile, $video_id . PHP_EOL); //video id
            fwrite($myfile, "t"); //is sending file flag
            fclose($myfile);

            $myfile = fopen("caption.txt", "w") or die("Unable to open file!");
            fwrite($myfile, $caption . PHP_EOL); //message id
            fclose($myfile);

            $params = array(
                'chat_id' => $chat_id,
                'video' => $video_id,
                'caption' => $caption . PHP_EOL . '@kgut_bot',
                "from" => '@kgut_bot',


                'reply_markup' => array(
                    'keyboard' => array(array($F_ERSAL_SHAVAD . $e_yes), array($F_ERSAL_NASHAVAD . $e_no)),
                    'one_time_keyboard' => true,
                    'resize_keyboard' => true)
            );

            apiRequest("sendVideo", $params);


        } elseif ($sending_document === "t") {

            $myfile = fopen("document_info.txt", "w") or die("Unable to open file!");
            fwrite($myfile, $document_id . PHP_EOL); //document id
            fwrite($myfile, "t"); //is sending file flag
            fclose($myfile);

            $myfile = fopen("caption.txt", "w") or die("Unable to open file!");
            fwrite($myfile, $caption . PHP_EOL); //document id
            fclose($myfile);

            $params = array(
                'chat_id' => $chat_id,
                'document' => $document_id,
                'caption' => $caption . PHP_EOL . '@kgut_bot',
                "from" => '@kgut_bot',


                'reply_markup' => array(
                    'keyboard' => array(array($F_ERSAL_SHAVAD . $e_yes), array($F_ERSAL_NASHAVAD . $e_no)),
                    'one_time_keyboard' => true,
                    'resize_keyboard' => true)
            );

            apiRequest("sendDocument", $params);


        } elseif ($text === $F_UNI_ADDRESS) {

            $params = array(
                'chat_id' => $chat_id,
                'phone_number' => "03433776611",
                'first_name' => 'دانشگاه تحصیلات تکمیلی کرمان'

            );
            apiRequest("sendContact", $params);

            $resp = $F_UNI_ADDRESS_DETAIL . PHP_EOL
                . $F_UNI_FAX . PHP_EOL
                . $F_UNI_POSTCODE . PHP_EOL
                . $F_UNI_MAIL . PHP_EOL;
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => $resp, 'reply_markup' => array(
                'keyboard' => $keyboard,
                'one_time_keyboard' => true,
                'resize_keyboard' => true)));


        } elseif ($text === $F_COMMENTS) {

            $resp = "شما دانشجویان عزیز می توانید انتقادات و پیشنهادات خود را از طریق آی دی زیر برای ما ارسال فرمایید" . PHP_EOL;
            $resp .= "@MojRaj";
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => $resp, 'reply_markup' => array(
                'keyboard' => $keyboard,
                'one_time_keyboard' => true,
                'resize_keyboard' => true)));


        } elseif ($text === $F_FOOD) {

            $resp = '<a href="http://uas.kgut.ac.ir">uas.kgut.ac.ir</a>';
            apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => $resp, 'parse_mode' => 'html', 'reply_markup' => array(
                'keyboard' => $keyboard,
                'one_time_keyboard' => true,
                'resize_keyboard' => true)));


        } elseif (strpos($text, "/stop") === 0) {
            // stop now
        } else {
            apiRequestWebhook("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "text" => 'پیام نا معتبر'));
        }
    } else {
        apiRequestJson("sendMessage", array('chat_id' => $chat_id /*,"text" => 'پیام نا معتبر'*/, "text" => $imgID));
    }
}

define('WEBHOOK_URL', 'https://my-site.example.com/secret-path-for-webhooks/');
if (php_sapi_name() == 'cli') {
    // if run from console, set or delete webhook
    apiRequest('setWebhook', array('url' => isset($argv[1]) && $argv[1] == 'delete' ? '' : WEBHOOK_URL));
    exit;
}
$content = file_get_contents("php://input");
$update = json_decode($content, true);
if (!$update) {
    // receive wrong update, must not happen
    exit;
}


if (isset($update)) {
    processMessage($update);
}



