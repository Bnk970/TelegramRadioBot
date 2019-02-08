#!/bin/bash
screen -dmS radio -t user bash -c 'php user.php && bash'
screen -S radio -X screen -t bot bash -c 'php bot.php && bash'