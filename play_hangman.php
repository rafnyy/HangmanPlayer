<?php
ini_set('memory_limit', '4G');

// Method: POST, PUT, GET etc
// Data: array("param" => "value") ==> index.php?param=value
function CallAPI($method, $url, $data = false)
{
    $curl = curl_init($url);

    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        //'Content-Type: application/json',
        'Connection: Keep-Alive',
        'apptio-opentoken: ' . $GLOBALS['token'],
        ));

    switch ($method)
    {
        case "POST":
            curl_setopt($curl, CURLOPT_POST, 1);
            if ($data)
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            break;
        case "PUT":
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data)
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            break;
        default:
            if ($data)
                $url = sprintf(";s?%s", $url, http_build_query($data));
    }

    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($curl);
    $info = curl_getinfo($curl);

    curl_close($curl);

    return array('status_code' => $info['http_code'], 'result' => $result, 'json' => json_decode($result, true));
}

$json = file_get_contents('tree.json');
$root = json_decode($json, true); 

$url = "http://hangman-api.herokuapp.com/";
$response = CallAPI('POST', $url . "/hangman");
$token = $response['json']['token'];

$strike = 0;
foreach($root['children'] as $child) {
    if($child['state'] === $response['json']['hangman']) {   
        $node = $child;
    }
}

while(true) {
    $data = array();
    $data['token'] = $token;
    
    do {
        $data['letter'] = key($node['char_count']);
        $response = CallAPI('PUT', $url . "/hangman", $data);
        next($node['char_count']);
    } while($response['status_code'] !== 200);

    $token = $response['json']['token'];
    
    if($response['status_code'] === 200 && strpos($response['json']['hangman'], '_') === false) {
        print "Your guess of " . $data['letter'] . " was correct." . PHP_EOL;
        print $response['json']['hangman'] . "! You Won! with " . $strike . " strikes against you.";
        exit();
    }

    if($response['status_code'] === 200 && !$response['json']['correct']) {
        print "Your guess of " . $data['letter']. " was incorrect." . PHP_EOL;
        $strike++;
        print "Strike " . $strike . "!" . PHP_EOL;
    } else {
        print "Your guess of " . $data['letter'] . " was correct." . PHP_EOL;
        print "The new state of the game is " . $response['json']['hangman'] . PHP_EOL;
    }

    if($strike >= 5) {
        break;
    }
    
    foreach($node['children'] as $child) {
        if($child['state'] === $response['json']['hangman']) {   
            $node = $child;
        }
    }
}

print("Loser!" . PHP_EOL);

$data = array();
$data['token'] = $token;
$response = CallAPI('GET', $url . "/hangman?token=" . $token);
print("The answer is " . $response['json']['solution']);
?>
