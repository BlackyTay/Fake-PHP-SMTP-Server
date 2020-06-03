# FakeSMTPServerScript

FakeSMTPServerScript is just a basic and simple PHP script to build a fake SMTP server. 

## Installation
1. Setup the server address and port to be listen on, ***default config:***
```php
$address = 'smtp.goodsane.com';
$port = 6006;
```
2. Get a SSL certificate (Self-signed certificate will not be supported) 
3. Run the script with ```php index.php```
4. For default, the script will captures all email sent to it and print on terminal.

## SSL Certificate

Modify the path for certificate and private key files before run

```php
 'local_cert'        => '/home/mailblast/ssl/Cert.crt',
 'local_pk'          => '/home/mailblast/ssl/Private.key',
```

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