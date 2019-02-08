<?php

# Capture Ctrl+C
declare(ticks = 1);
function sig_handler($signo)
{
	die("Terminating safely...");
}

pcntl_signal(SIGINT, "sig_handler");

#include the ever-updating library...
if(!file_exists('madeline.php')) copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
include 'madeline.php';

class UserBot extends \danog\MadelineProto\EventHandler
{
	private $calls = [];
	private $debug_to_user = true;

	public function __construct($MadelineProto)
	{
		parent::__construct($MadelineProto);
		echo "Choose user now:\n";
		if (!$MadelineProto->get_self())
			$this->start();
	}

	public function onLoop()
	{
		# Keep signaling that the script is still active. Helps to know when the bot has crashed (last seen)
		$this->account->updateStatus(['offline' => false]);

		# If there are active calls
		if(count($this->calls))
		{
			# Fetch the settings. It also contains the station list
			$settings = json_decode(file_get_contents("settings.json"), true);
			# Loop over the active calls
			foreach($this->calls as $key => $call)
			{
				\danog\MadelineProto\Logger::log("Looping over the calls...");
				# id of the user on the other side of the line
				$other_id = $call['id'];
				# If the call is not active anymore
				if($call['call']->getCallState() === \danog\MadelineProto\VoIP::CALL_STATE_ENDED)
				{
					if($this->debug_to_user === true) $this->messages->sendMessage(['peer' => $other_id, 'message' => "Ending call..."]);
					# Name of the fifo for unconverted stream
					$in = "/tmp/" . $other_id . "_in.fifo";
					# Name of the fifo for converted stream
					$out = "/tmp/" . $other_id . "_out.fifo";
					/* This is not needed because for some reason, the processes don't exist by now.
						# Kill the process that downloads the stream
						echo "killing ".$call['curl']."\n";
						exec("kill " . $call['curl']);
						if($this->debug_to_user === true) $this->messages->sendMessage(['peer' => $other_id, 'message' => "Killed curl..."]);
						# Kill the process that converts the stream
						echo "killing ".$call['ffmpeg']."\n";
						exec("kill " . $call['ffmpeg']);
						if($this->debug_to_user === true) $this->messages->sendMessage(['peer' => $other_id, 'message' => "Killed ffmpeg..."]);
					*/
					# Delete the old fifos
					exec("rm -f " . $in);
					if($this->debug_to_user === true) $this->messages->sendMessage(['peer' => $other_id, 'message' => "Deleted $in"]);
					exec("rm -f " . $out);
					if($this->debug_to_user === true) $this->messages->sendMessage(['peer' => $other_id, 'message' => "Deleted $out"]);
					if($this->debug_to_user === true) $this->messages->sendMessage(['peer' => $other_id, 'message' => "Thank you for using this service.\n\nPowered by @MadelineProto."]);    # This was requested by the developer of @MadelineProto. TODO: change this to something less annoying.
					# Delete the ended call from the active calls list
					unset($this->calls[$key]);
				}
				# If the call is still active
				else
				{
					# If the user have changed the channel
					if(isset($settings['users'][$other_id]) && $call['station'] !== $settings['stations'][$settings['users'][$other_id]])
					{
						# Name of the fifo for unconverted stream
						$in = "/tmp/" . $other_id . "_in.fifo";
						# Name of the fifo for converted stream
						$out = "/tmp/" . $other_id . "_out.fifo";
						# Kill the process that downloads the stream
						exec("kill " . $call['curl']);
						# Kill the process that converts the stream
						exec("kill " . $call['ffmpeg']);
						# Delete the old fifos
						exec("rm -f " . $in);
						# See above line
						exec("rm -f " . $out);
						# I''m actually not really sure if I really need to delete it. In fact, I think it''s better if I don''t. TODO: try deleting this line (not the files)...
						# TODO: delete also the line below, because it's part of the lines above...
						exec("mkfifo " . $in);        # Recreate the fifos
						exec("mkfifo " . $out);

						# Start downloading the new stream
						$curl = exec("curl --output " . $in . " " . $settings['stations'][$settings['users'][$other_id]] . " -s" . ' > /dev/null 2>&1 & echo $!; ');
						# Start converting the stream
						$ffmpeg = exec("ffmpeg -i " . $in . " -f s16le -ac 1 -ar 48000 -acodec pcm_s16le " . $out . " -y < /dev/null" . ' > /dev/null 2>&1 & echo $!; ');
						# Save the PIDs so it can be killed later
						$this->calls[$key]['curl'] = $curl;
						$this->calls[$key]['ffmpeg'] = $ffmpeg;
						# Save the currently playing station, so it can be compared lated and checked if changed
						$this->calls[$key]['station'] = $settings['stations'][$settings['users'][$other_id]];
						# Play the new stream
						$call['call']->play($out);
					}
				}
			}
		}
	}

	public function onUpdateNewMessage($update)
	{
		# Make sure we don't spam ourself or telegram...
		if($update['message']['from_id'] == "349157599" || $update['message']['from_id'] == "777000") return;
		# Ignore outgoing messages
		if(isset($update['message']['out']) && $update['message']['out']) return;

		# Answer with a description of the bot
		#$this->messages->sendMessage(['peer' => $update['message']['from_id'], 'message' => 'תתקשר אליי כדי לשמוע רדיו!']);
	}

	public function onUpdatePhoneCall($update)
	{
		# If the call is still active
		if(is_object($update['phone_call']) && $update['phone_call']->getCallState() === \danog\MadelineProto\VoIP::CALL_STATE_INCOMING)
		{
			$this->messages->sendMessage(['peer' => '@BnK970', 'message' => 'New phone call']);
			# Save the other side's ID
			$other_id = $update['phone_call']->getOtherID();
			# Get the settings from the JSON file
			$settings = json_decode(file_get_contents("settings.json"), true);

			# Choose the right station for the user
			if(isset($settings['users'][$other_id])) $station = $settings['stations'][$settings['users'][$other_id]];
			else
				$station = "http://gly-switch1.level1.co.il:9000/live";

			# Report to the developer that the bot was called
			$other_chat = $this->get_pwr_chat($other_id);
			$msg = $other_id . " ";
			if(isset($other_chat['first_name'])) $msg .= $other_chat['first_name'] . " ";
			if(isset($other_chat['last_name'])) $msg .= $other_chat['last_name'] . " ";
			if(isset($other_chat['username'])) $msg .= "(@" . $other_chat['username'] . ") ";
			$msg .= " has just called me!";
			$this->messages->sendMessage(['peer' => '@BnK970', 'message' => $msg]);

			# Files that contain the streams
			$in = "/tmp/" . $other_id . "_in.fifo";
			$out = "/tmp/" . $other_id . "_out.fifo";

			# Get inline message
			$results = $this->messages->getInlineBotResults(['bot' => '@radio_ilbot', 'peer' => $other_id, 'query' => (string)$other_id, 'offset' => ""]);
			$this->messages->sendInlineBotResult(['peer' => $other_id, 'query_id' => $results['query_id'], 'id' => '666']);


			if($this->debug_to_user === true) $this->messages->sendMessage(['peer' => $other_id, 'message' => "Answering the phone..."]);
			exec("rm -f " . $in);
			if($this->debug_to_user === true) $this->messages->sendMessage(['peer' => $other_id, 'message' => "Deleted $in"]);
			exec("rm -f " . $out);
			if($this->debug_to_user === true) $this->messages->sendMessage(['peer' => $other_id, 'message' => "Deleted $out"]);
			exec("mkfifo " . $in);
			if($this->debug_to_user === true) $this->messages->sendMessage(['peer' => $other_id, 'message' => "Created $in"]);
			exec("mkfifo " . $out);
			if($this->debug_to_user === true) $this->messages->sendMessage(['peer' => $other_id, 'message' => "Created $out"]);
			$curl2 = '';
			$curl = exec("curl --output " . $in . " " . $station . " -s" . ' > /dev/null 2>&1 & echo $!; ', $curl2);
			var_dump($curl);
			var_dump($curl2);
			if($this->debug_to_user === true) $this->messages->sendMessage(['peer' => $other_id, 'message' => "Downloading stream..."]);
			$ffmpeg = exec("ffmpeg -i " . $in . " -f s16le -ac 1 -ar 48000 -acodec pcm_s16le " . $out . " -y < /dev/null" . ' > /dev/null 2>&1 & echo $!; ');
			if($this->debug_to_user === true) $this->messages->sendMessage(['peer' => $other_id, 'message' => "Converting stream..."]);
			$temp = $call['call'] = $update['phone_call']->accept();

			# If we actually answered the phone successfully
			if($temp !== false)
			{
				$temp = $temp->play($out);
				if($this->debug_to_user === true) $this->messages->sendMessage(['peer' => $other_id, 'message' => "Playing radio..."]);
				$call['id'] = $other_id;
				$call['station'] = $station;
				$call['curl'] = $curl;
				$call['ffmpeg'] = $ffmpeg;
				array_push($this->calls, $call);
			}
		}
	}
}

$MadelineProto = new \danog\MadelineProto\API('radio.madeline', ['app_info' => ['api_id' => 6, 'api_hash' => "eb06d4abfb49dc3eeb1aeb98ae0f581e", 'app_version' => '0.1']]);
$MadelineProto->setEventHandler('\UserBot');
$MadelineProto->loop();
?>