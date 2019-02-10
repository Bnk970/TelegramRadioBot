## Radio Bot for Telegram

### Requirements

* A Telegram user on which you can run scripts like this. If you have a lot of users you'll get a lot of spam, so try to avoid doing this on your main account.
* A bot token
* A server to run the bot on

### How to use (Linux)

1. Clone the repo to your server.
2. Edit settings.json and fill in bot_token and add stations as you please.
3. Run `bash run_both.sh`
4. For the first run: run `screen -r radio` to enter the GNU Screen of the Radio Bot and fill in the phone number in the user window (Ctrl + a, " to enter window selection mode)
5. In case of errors, run each part of the bot manually to see the whole log after the crash. Run the user part using `php user.php` and the bot part using `php bot.php`. Report bugs to [@BnK970 on Telegram](t.me/BnK970) and let me know what the context of the message is. Don't just send _"it's not working"_. I need more info than that.

### Credits
Thanks for [BnK970](t.me/bnk970) for writing this bot, and thanks for [Daniil Gentili](t.me/danogentili) for writing the library for this ([MadelineProto](https://github.com/danog/MadelineProto)).
