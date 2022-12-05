<?php

if (!file_exists('/bot/madeline.php')) {
    copy('https://phar.madelineproto.xyz/madeline.php', '/bot/madeline.php');
}
include '/bot/madeline.php';
include '/bot/pcntl.php';
include '/bot/useful.php';


class DataBase
{
    public function __construct($host = "jora13y6.beget.tech", $user = "jora13y6_eup", $password = "3EuvV*nS", $database = "jora13y6_eup")
    {
        $this->DataBase = new mysqli($host, $user, $password, $database);
        if ($this->DataBase->connect_errno) {
            throw new Exception("Failed to connect to MySQL: (" . $this->DataBase->connect_errno . ") " . $this->DataBase->connect_error);
            print $this->DataBase->host_info . "\n";
        }
        return $this->DataBase;
    }
    public function query($query, $close = false)
    {
        $result = $this->DataBase->query($query);
        if ($this->DataBase->errno) {
            throw new Exception("Failed to send a query to MySQL: (" . $this->DataBase->errno . ") " . $this->DataBase->error);
            print $this->DataBase->host_info . "\n";
        }
        if ($close) $this->DataBase->close();
        return $result;
    }
}

class Telegram
{
    private $sessions;
    private $channels = [
        1395872617 => [
            'ru' => 'Спорт',
            'en' => 'Sport',
        ],
        1172111578 => [
            'ru' => 'КиберСпорт',
            'en' => 'CyberSport',
        ]
    ];
    public function __construct(array $sessions)
    {
        $this->sessions = &$sessions;
    }

    public function getChannelName(int $channel, string $lang)
    {
        return $this->channels[$channel][$lang];
    }

    public function import_contacts(array $contacts)
    {
        foreach ($contacts as $contact) {
            $inputs[] = array_merge([
                '_' => 'inputPhoneContact',
                'client_id' => mt_rand(10000, 99999),
            ], $contact);
        }
        try {
            $ImportedContacts = $this->sessions['USER']->contacts->importContacts(['contacts' => $inputs]);
        } catch (\Exception $e) {
            $error = 'importContacts (45): ' . $e->getMessage() . "\n";
            $this->sessions['BOT']->messages->sendMessage(['peer' => 565324826, 'message' => $error]);
        }
        foreach ($ImportedContacts['imported'] as $user) $users[] = $user['user_id'];
        return $users;
    }

    public function join(int $channel)
    {
        try {
            return $this->sessions['USER']->channels->joinChannel(['channel' => "channel#$channel"]);
        } catch (\Exception $e) {
            $error = $channel.': join_channel (76): ' . $e->getMessage() . "\n";
            $this->sessions['BOT']->messages->sendMessage(['peer' => 565324826, 'message' => $error]);
        }
    }

    public function add(array $peers)
    {
        foreach ($peers as $session)
            foreach ($session as $channel => $users) {
                $this->join($channel);
                if (is_array($users[0])) $users = $this->import_contacts($users);
                try{
                    $this->sessions['USER']->channels->inviteToChannel(['channel' => "channel#$channel", 'users' => $users]); // Adding users to channel
                    return $this->checkUsers($users, $channel); // Checking users being in channel
                } catch (\Exception $e) {
                    $error = 'invite_user (32): ' . $e->getMessage() . "\n";
                    $this->sessions['BOT']->messages->sendMessage(['peer' => 565324826, 'message' => $error]);
                }
            }
    }

    public function checkUsers(array $users, int $channel)
    {
        try {
            $participants = $this->sessions['USER']->channels->getParticipants(['channel' => "channel#$channel", 'filter' => ['_' => 'channelParticipantsRecent'], 'offset' => 0, 'limit' => 250]);
        } catch (\Exception $e) {
            $error = 'getParticipants (16): ' . $e->getMessage() . "\n";
            $this->sessions['BOT']->messages->sendMessage(['peer' => 565324826, 'message' => $error]);
        }
        foreach ($participants as $participant) {
            $participantsFetched[] = $participants['channelParticipant']['user_id'];
        }
        $diff = array_diff_assoc($users, $participantsFetched);
        return $diff;
    }

    public function send($peer, $message, $session = "BOT")
    {
        if ($peer == "dev") $peer = 565324826;
        try {
            $this->sessions[$session]->messages->sendMessage(['peer' => $peer, 'message' => $message, 'silent' => true]);
        } catch (\Exception $e) {
            $this->sessions['BOT']->messages->sendMessage(['peer' => 565324826, 'message' => $e, 'silent' => true]);
        }
    }
}

class execute
{
    public $sleep;
    public $telegram;
    public $job;
    public $useful;
    public function __construct($sessions)
    {
        $this->telegram = new Telegram($sessions);
        $this->useful = new useful();
    }
    public function checkDB()
    {
        $date = $this->useful->date_to_words(date("Y-m-d"));
        $db = new DataBase();
        $result = $db->query("SELECT * FROM for_add WHERE status BETWEEN 600 AND 700");
        while ($person = $result->fetch_assoc()) 
            switch ($person['status']) {
                    case 600:
                    case 700:
                        $case = [
                            600 => "add1",
                            700 => "remove",
                        ][$person['status']]; //

                        $ids[$case] = $person['id']; // Identifications for next queries

                        if (!empty($person['user_id'])) $people[$case]['BOT'][$person['channel']][] = $person['user_id'];
                        else
                        if (!empty($person['phone'])) {
                            $people[$case]['USER'][$person['channel']][] = [
                                'first_name' => $this->telegram->getChannelName($person['channel'], 'ru'),
                                'last_name' => "До {$date['day']} {$date['month']}",
                                'phone' => $person['phone'],
                            ];
                        }
                        break;
                }
        $result->close();

        if (isset($people['add']) && $people['add']) {
            $added = $this->telegram->add($people['add']); // $added can be array with 'not added people' or 'all'
            
            if (isset($added) && !empty($added)) {
                $ids['add'] = implode(", ", $ids['add']); // Identifications for next queries but imploded
                $db->query("UPDATE SET status = 700 WHERE id IN($ids)");
            }
            if (is_array($added)) {
                $fails = implode(", ", $added); // failled to add users imploded
                $db->query("UPDATE SET status = 710 WHERE id IN($fails)");
            }
        }
        
        if (isset($people['remove']) && $people['remove']) {
            #$this->telegram->remove($people['remove']);
            $Chat = $this->telegram->checkUsers([], 1395872617);
            $res = json_encode($Chat, JSON_PRETTY_PRINT);
            $this->telegram->send("dev", $res);
        }
            
    }
    public function loop($function)
    {
        while (true) {
            $function();
            sleep($this->sleep);
        }
    }
}


/* Start Madeline Session as an user */
$MadelineProtoBOT = new \danog\MadelineProto\API('/bot/bot.madeline');
$MadelineProto = new \danog\MadelineProto\API('/bot/session_2.madeline');
$MadelineProto->loop(function() use (&$MadelineProto,  &$MadelineProtoBOT) {
    $MadelineProto->start();

    $peer['sport'] = [
        '_' => 'inputPeerChannel',
        'channel_id' => 1395872617,
        'access_hash' => -7976650626730682015,
    ];

    $peer['cybersport'] = [
        '_' => 'inputPeerChannel',
        'channel_id' => 1172111578,
        'access_hash' => 7228125093539335207,
    ];
    
    #$MadelineProto->channels->joinChannel(['channel' => $peer['sport']]);
    #$Chat = $MadelineProto->messages->exportChatInvite();
    #$res = json_encode($Chat, JSON_PRETTY_PRINT);
    #print_r($res);
    #print $link = $Chat['link']."\r\n";

    $execute = new execute(['USER' => &$MadelineProto, 'BOT' => &$MadelineProtoBOT]);
    $execute->sleep = 5;
    $execute->loop(function () use ($execute) {
        $execute->checkDB();
    });
});