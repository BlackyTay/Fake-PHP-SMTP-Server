# FakeSMTPServerScript

FakeSMTPServerScript is just a basic and simple PHP script to build a fake SMTP server. 

## Installation
1. ~~Setup the server address and port to be listen on, ***default config:***~~
```php
$address = 'smtp.goodsane.com';
$port = 6006;
```
2. Now the server will get the configuration from database
3. Get and [setup a SSL certificate](#ssl-certificate) (Self-signed certificate will not be supported)
4. [Run the script](#run-the-script)
5. ~~For default, the script will captures all email sent to it and print on terminal.~~
6. Now the script will splits the mail content and store them accordingly in database, and try to send the mail with SMTP account from database

## SSL Certificate
Modify the path for certificate and private key files before run

```php
 'local_cert'        => '/home/mailblast/ssl/Cert.crt',
 'local_pk'          => '/home/mailblast/ssl/Private.key',
```

## Run The Script

#### Basic Terminal Command (Will be killed after terminal session is ended)
1. In putty terminal, run ```php your_script_name.php```
2. To kill it, run [Kill Script Part 2](#to-kill-running-script-on-server-part-2-initial-by-terminal)
3. If it is abandoned (accidentally terminal session ended), it cannot be bring to foreground in another session and can only be killed by [Kill Script Part 1](#to-kill-running-script-on-server-part-1-initial-by-cron)

#### Screen Command (Can be accessed across terminal sessions)
1. In putty terminal, run ```screen```, and press RETURN to enter Screen process
2. Run ```php your_script_name.php```
3. Detach the script process with current terminal (Bring it to background) with hotkeys ```Ctrl + D``` and ```Ctrl + A```
4. Then you can safely end current terminal session
5. To bring the process back to foreground in any terminal session, run ```screen -r```


## Pending Mails Checker Script (\**_New_)
1. This script will check all pending mail in database and try to send them immediately.
2. It will exit upon all pending mail are processed (regardless success or fail).
3. It is designed for CRON job once an hour.


## To Kill Running Script

#### To Kill Running Script On Server (Part 1: Initial By CRON)
1. Login in Putty
2. Search your running script's PID with command: ```ps aux | grep 'your_script_name_here'```
3. Get the PID of your script from the output
4. Kill the running script with command: ```kill -9 your_script_PID_here```

#### To Kill Running Script On Server (Part 2: Initial By Terminal)
1. In Putty, terminate your running script with hotkeys: ```Ctrl + C```
2. ***NEVER PRESS*** ```Ctrl + Z``` to terminate running script, it will lead to occupied port. 
3. If you accidentally pressed ```Ctrl + Z``` and terminated the script, you can: 
   - Close and reopen your current terminal process, or
   - Proceed to [Kill Script Part 1](#to-kill-running-script-on-server-part-1-initial-by-cron)


## References
*These resources will be helpful for anyone who want to know behind the codes :*
- [axllent / fake-smtp](https://github.com/axllent/fake-smtp)
- [ReachFive / fake-smtp-server](https://github.com/ReachFive/fake-smtp-server)
- [Writing a webserver in pure PHP - Tutorial](http://station.clancats.com/writing-a-webserver-in-pure-php/)
- [PHP Simple TCP/IP server](https://riptutorial.com/php/example/29644/simple-tcp-ip-server)
- [Beej's Guide to Network Programming](http://beej.us/guide/bgnet/html/)
- [List of All SMTP Commands and Response Codes](https://blog.mailtrap.io/smtp-commands-and-responses/#STARTTLS)
- [What Happens in a TLS Handshake?](https://www.cloudflare.com/learning/ssl/what-happens-in-a-tls-handshake/)
- [Taking a Closer Look at the SSL/TLS Handshake](https://www.thesslstore.com/blog/explaining-ssl-handshake/)
- [Debugging SMTP Conversations Part 1: How to Speak SMTP](https://aws.amazon.com/blogs/messaging-and-targeting/debugging-smtp-conversations-part-1-how-to-speak-smtp/)
- [HOWTO: PHP TCP Server/Client with SSL Encryption using Streams](https://www.leenix.co.uk/news-howto-php-tcp-serverclient-with-ssl-encryption-using-streams-8)

## License
[MIT](https://choosealicense.com/licenses/mit/)