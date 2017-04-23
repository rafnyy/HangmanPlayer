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

function addToNode($word, $node) {
    if($word == $node['state']) {
        return $node;
    }

    // add to words
    array_push($node['words'], $word);

    $blank_state = "";
    for($j = 0; $j < strlen($word); $j++) {
        $char_count[$word[$j]]++;
        $blank_state .= '_';
    }
    
    if($node['state'] === '') {
        return findAndUpdateChild($word, $blank_state, $node);     
    }

    $current_guess = $node['guess'];
    // increment char_count and set already guessed chars to zero
    print_r($char_count);
    foreach($char_count as $letter => $count) {
        // If $letter is in $state, we have already guessed this
        if (strpos($node['state'], $letter) !== false) {
            $node['char_count'][$letter] = 0;
        } else {
            $node['char_count'][$letter] += $char_count[$letter];
        }
    }
    arsort($node['char_count']);

    reset($node['char_count']);
    $new_guess = key($node['char_count']);

    // if guess is the same, go to appropriate child and repeat
    if($new_guess === $current_guess) {
        $state = updateState($node['state'], $word, $new_guess);
        
        return findAndUpdateChild($word, $state, $node);     
    } else { // if guess is different, throw out children and rebuild
        $node['guess'] = $new_guess;
        $node['children'] = array();
        
        foreach($node['words'] as $childWord) {
            $child_node_exists = false;
            $new_state = updateState($node['state'], $childWord, $new_guess);

            foreach($node['children'] as $child_node) {
                if($child_node['state'] === $new_state) {
                    $child_node = addToNode($childWord, $child_node);
                    $child_node_exists = true;
                    break;
                }
            }                

            if(!$child_node_exists) {
                $new_node = newNode($new_state);
                $new_node = addToNode($childWord, $new_node);
                array_push($node['children'], $new_node);
                return $node;
            }
        }
    }
}

function findAndUpdateChild($word, $state, $node) {
    $child_index = 0;
    foreach($node['children'] as $child) {
        if($child['state'] === $state) {
            //repeat on $child
            $child = addToNode($word, $child);
            $node['children'][$child_index] = $child;
            return $node;
        }
        $child_index++;
    }

    // if no matching child node, create it
    $new_node = addToNode($word, newNode($state));
    array_push($node['children'], $new_node);
    return $node;
}

function updateState($state, $word, $guess) {
    for($j = 0; $j <= strlen($word); $j++) {
        if(substr($word, $j, 1) === $guess) {
            $state[$j] = $guess;    
        }
    }
    
    return $state;
}

function newNode($state) {
    $letters = array("a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z");
    $empty_char_count = array();
    foreach($letters as $letter) {
        $empty_char_count[$letter] = 0;
    }

    $new_node['state'] = $state;
    $new_node['guess'] = '';
    $new_node['words'] = array();
    $new_node['char_count'] = $empty_char_count;
    $new_node['children'] = array();

    return $new_node;
}

$response = CallAPI("GET", "https://raw.githubusercontent.com/despo/hangman/master/words");
//$word_str = strtolower($response['result']);
$word_str = "aak\nkok\noak\nbbb";

$root = newNode('');

$words = explode("\n", $word_str);

$wordCount = 0;
foreach($words as $word) {
    strlen($word);
    
    $root = addToNode($word, $root);
    print("Adding " . $word . " to decision tree." . PHP_EOL);
    $wordCount++;
}

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
    $data['letter'] = $node['guess'];
    foreach($node['children'] as $child) {
        if($child['state'] === $response['json']['hangman']) {   
            $node = $child;
        }
    }
    
    $response = CallAPI('PUT', $url . "/hangman", $data);
    $token = $response['json']['token'];

    if(strpos($response['json']['hangman'], '_') === false) {
        print "You Won! with " . $strike . " strikes against you.";;
        exit();
    }

    if(!$response['json']['correct']) {
        print "Your guess of " . $node['guess'] . " was incorrect." . PHP_EOL;
        $strike++;
        print "Strike " . $strike . "!" . PHP_EOL;
    } else {
        print "Your guess of " . $guess . "  was correct." . PHP_EOL;
        print "The new state of the game is " . $response['json']['hangman'];
    }

    if($strike >= 5) {
        break;
    }
}

print("Loser!" . PHP_EOL);

$data = array();
$data['token'] = $token;
$response = CallAPI('GET', $url . "/hangman?token=" . $token);
print("The answer is " . $response['json']['solution']);
?>
