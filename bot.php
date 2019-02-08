<?php
#include the ever-updating library...
if (!file_exists('madeline.php'))
	copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
include 'madeline.php';

class Bot extends \danog\MadelineProto\EventHandler
{
	# Generates the buttons for the station selection
	private function generate_inline($id)
	{
		$settings = json_decode(file_get_contents("settings.json"), true);
		# Rows and keys of the inline keyboard
		$rows = [];
		$keys = [];
		# Unicode char that forces RTL direction
		$rtl = mb_convert_encoding('&#x200f;', 'UTF-8', 'HTML-ENTITIES');
		$stations = $settings['stations'];
		# Since we don't want to waste space on the settings file, the list of the statios isn't saved on it's own, but as pairs of keys (names) and values (source stream) now we loop over this object and extract the names
		while (current($stations))
		{
			array_push($keys, key($stations));
			next($stations);
		}
		# Looping over the list of stations and placing them into the keyboard in groups of two
		for ($i = 0; $i < count($settings['stations']); $i += 2)
		{
			$buttons = [];
			$button = [];
			for ($j = 0; $j <= 1; $j++)
			{
				if (isset($keys[$i + $j]))
				{
					# Check if this is the selected station and mark it for the user
					if (isset($settings['users'][$id]) && $settings['users'][$id] == $keys[$i + $j])
						$text = "✅ ";
					else
						$text = "☑️ ";
					$button[$j] = ['_' => 'keyboardButtonCallback', 'text' => $rtl . $text . $settings['station_names'][$keys[$i + $j]], 'data' => $keys[$i + $j]];
					array_push($buttons, $button[$j]);
				}
			}

			$row = ['_' => 'keyboardButtonRow', 'buttons' => $buttons];
			array_push($rows, $row);
		}
		$reply_markup = ['_' => 'replyInlineMarkup', 'rows' => $rows];
		return $reply_markup;
	}

	public function __construct($MadelineProto)
	{
		parent::__construct($MadelineProto);
	}

	public function onAny($update)
	{
		\danog\MadelineProto\Logger::log($update);
	}

	public function onUpdateNewMessage($update)
	{
		# Make sure we don't spam ourself or telegram...
		if ($update['message']['from_id'] == "349157599" || $update['message']['from_id'] == "777000") return;
		# Ignore outgoing messages
		if (isset($update['message']['out']) && $update['message']['out']) return;

		# Anwer with a description of the bot
		$this->messages->sendMessage(['peer' => $update['message']['from_id'], 'message' => 'אני עוזר לך לשמוע רדיו אצל [הבוט הראשי](mention:@radio_il)', 'parse_mode' => "markdown"]);
	}

	public function onUpdateBotInlineQuery($update)
	{
//		if ($update['user_id'] !== 349157599)
//			return;
		\danog\MadelineProto\Logger::log($update);
		$settings = json_decode(file_get_contents("settings.json"), true);
		$other_id = $update["query"];
		$station_name = isset($settings['users'][$update["query"]]) ? $settings['station_names'][$settings['users'][$update["query"]]] : "גלי ישראל (ברירת מחדל)";
		$query = "אתם מאזינים כעת ל" . $station_name . ".\nלבחירת תחנה לחץ:";
		$id = $update['query'];
		if ($id == "")
			$id = $update['user_id'];
		$reply_markup = $this->generate_inline($id);

		$message = $query;
		$send_message = ['_' => 'inputBotInlineMessageText', 'message' => $message, 'reply_markup' => $reply_markup];
		$results = [['_' => 'inputBotInlineResult', 'id' => '666', 'type' => 'article', 'title' => 'send', 'send_message' => $send_message]];
		$inline_data = ['query_id' => $update['query_id'], 'results' => $results, 'cache_time' => 0];
		try
		{
			$this->messages->setInlineBotResults($inline_data);
		}
		catch (Exception $e)
		{
			\danog\MadelineProto\Logger::log($e);
		}
	}

	public function onUpdateInlineBotCallbackQuery($update)
	{
		$other_id = $update['user_id'];
		$json = json_decode(file_get_contents("settings.json"), true);
		$json['users'][$other_id] = (string)$update['data'];
		file_put_contents("settings.json", json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		try
		{
			$settings = json_decode(file_get_contents("settings.json"), true);
			$station_name = isset($settings['users'][$other_id]) ? $settings['station_names'][$settings['users'][$other_id]] : "גלי ישראל (ברירת מחדל)";
			$message = "אתם מאזינים כעת ל" . $station_name . ".\nלבחירת תחנה לחץ:";
			$reply_markup = $this->generate_inline($update['user_id']);
			$this->messages->editInlineBotMessage(['id' => $update['msg_id'], 'message' => $message, 'reply_markup' => $reply_markup]);
		}
		catch (Exception $e)
		{
			echo "\n\nError: " . $e->getMessage() . "\n\n";
		}
	}
}
$settings = json_decode(file_get_contents("settings.json"), true);
$MadelineProto = new \danog\MadelineProto\API('radiobot.madeline', ['app_info' => ['api_id' => 6, 'api_hash' => "eb06d4abfb49dc3eeb1aeb98ae0f581e", 'app_version' => '0.7']]);
if (!$MadelineProto->get_self())
	$MadelineProto->bot_login($settings['bot_token']);
$MadelineProto->setEventHandler('\Bot');
$MadelineProto->loop();
?>