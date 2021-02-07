<p align="center">
<a href="https://packagist.org/packages/pushradar/pushradar-server-php"><img src="https://poser.pugx.org/pushradar/pushradar-server-php/v/stable.svg" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/pushradar/pushradar-server-php"><img src="https://poser.pugx.org/pushradar/pushradar-server-php/d/total.svg" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/pushradar/pushradar-server-php"><img src="https://poser.pugx.org/pushradar/pushradar-server-php/license.svg" alt="License"></a>
</p>

## PushRadar PHP Server Library

[PushRadar](https://pushradar.com) is a realtime API service for the web. The service uses a simple publish-subscribe model, allowing you to broadcast "messages" on "channels" that are subscribed to by one or more clients. Messages are pushed in realtime to those clients.

This is PushRadar's official PHP server library.

## Prerequisites

In order to use this library, please ensure that you have the following:

- PHP 7, PHP 8
- A PushRadar account - you can sign up at [pushradar.com](https://pushradar.com)
- [`curl`](https://secure.php.net/manual/en/book.curl.php) and [`json`](https://secure.php.net/manual/en/book.json.php) extensions enabled

## Installation

The easiest way to get up and running is to install the library using [Composer](http://getcomposer.org/). Run the following command in your console:

```bash
composer require pushradar/pushradar-server-php "^3.0"
```

## Broadcasting Messages

```php
$radar = new \PushRadar\PushRadar('your-secret-key');
$radar->broadcast('channel-1', ['message' => 'Hello world!']);
```

## Receiving Messages

```html
<script src="https://pushradar.com/js/v3/pushradar.min.js"></script>
<script>
    var radar = new PushRadar('your-public-key');
    radar.subscribe.to('channel-1', function (data) {
        console.log(data.message);
    });
</script>
```

## Private Channels

Private channels require authentication and start with the prefix **private-**. We recommend that you use private channels by default to prevent unauthorised access to channels.

You will need to set up an authentication endpoint that returns a token using the `auth(...)` method if the user is allowed to subscribe to the channel. For example:

```php
if (/* user can join channel */ true) {
    return json_encode(['token' => $radar->auth($channelName)]);
}
```

Then register your authentication endpoint by calling the `auth(...)` method client-side:

```javascript
radar.auth('/auth');
```

## License

Copyright 2021, PushRadar. PushRadar's PHP server library is licensed under the MIT license:
http://www.opensource.org/licenses/mit-license.php