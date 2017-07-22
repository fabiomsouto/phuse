# Phuse - A no-frills fuse implementation for PHP
This applications implements fuses for PHP. Right now it offers an APC-backed unsafe fuse.
It is greatly inspired by [jlouis](https://github.com/jlouis)' implementation of fuses in erlang, which you can find [here](https://github.com/jlouis/fuse).

## Installation

Instalation is as simple as running

```bash
$ composer require fabiomsouto/phuse
```

## Changelog

This project follows [semantic versioning](http://semver.org).

### 1.0.0
[Added]
- A Fuse is now an SplSubject too. This way you can attach SplObserver instances to it, and get notified when the fuse melts or restarts.
- Added a phpunit.xml specification, so you can run the unit tests in a breeze.

[Changed]
- A couple of links in the README.md weren't working, fixed those.
- Added some assertions to some unit tests, for extra safety.

### 0.0.1
[Added]
- UnsafeAPCFuse implementation: an asynchronous fuse, that relies on APC to store state.
- FuseBox, a Factory to generate fuses
- Basic unit tests

## What is a fuse?
A fuse is a component that short-circuits internally after melting, similarly to a real life fuse.

## What can it do for me?
A fuse can help your application be more reliable when it needs to communicate with external systems. Let's say that
your application A communicates with an application B. If B starts timing out, or throwing errors, how would your application
behave? Specifically in the case of PHP, if you have a REST service, and it needs to synchronously hit an external endpoint,
latencies in the external service will cause your own service to have increased latencies, locking up your workers, etc.
A fuse can sit in between. Whenever you detect a fault, you *melt* your fuse. After a number of melts, the fuse *blows*,
and it will not contact your service for a while, breaking this dependency chain. Your service can then fail a lot faster,
or react accordingly (by contacting a backup service for example).

## How do I use a fuse (tutorial)?
Easy peasy. Start by instantiating a fuse:
```php
$M = 10;
$T = 100;
$R = 1000;
$fuse = FuseBox::getUnsafeApcInstance("database", $M, $T, $R);
```
This will setup a fuse, named `database`. `$M` is the number of melts the fuse can withstand until it melts. `$T` is the
time interval during which these melts can occur. So in this case, if 10 melts happen in 100ms, the fuse blows. After the
fuse blows, it will only restart after `$R` ms, in this case 1000ms.

After your fuse is setup, your app can ask about its state:
```php
$M = 10;
$T = 100;
$R = 1000;
$fuse = FuseBox::getUnsafeApcInstance("database", $M, $T, $R);
if ($fuse->ok()) {
    // do whatever
}
```

This is the happy situation. But now let's say that you're doing a POST to some endpoint, and it errors out:
```php
$M = 10;
$T = 100;
$R = 1000;
$fuse = FuseBox::getUnsafeApcInstance("database", $M, $T, $R);

...

try {
   $result = $curl->post($url, $data);
}
catch (Exception $e) {
    $fuse->melt();
}
```
You're signaling the fuse that something's wrong. If the fuse melts too many times in a certain time frame, it blows, and
stays that way until it cools down.

## How do I monitor the fuses?
Every fuse that inherits from the Fuse interface is also an SplSubject. As such, you can create a SplObserver and register
it along the fuse. Each time the fuse blows or restarts, the Observer will be notified:

```php
class AFuseObserver implements SplObserver {
    public function update(SplSubject $subject)
    {
        $blown = $subject->blown();
        echo "This fuse is now ";
        echo $blown? "blown\n" : "not blown\n";
    }
}

...

$M = 10;
$T = 100;
$R = 1000;
$fuse = FuseBox::getUnsafeApcInstance("database", $M, $T, $R);
$observer = new AFuseObserver();
$fuse->attach($observer);

// when the fuse melts you will see something in your terminal
$fuse->melt();
// outputs "This fuse is now blown" when the threshold is reached

...
// after 1000ms...
$fuse->ok();
// outputs "This fuse is now not blown" when the restart period ends
```

Keep in mind, notifying observers can take a long time. As such, keep the update call lean and make sure you're not registering
a lot of observers in each fuse, as notifying all of them is an O(n) operation.

## Performance
This will be available soon.

## Tests
Some PHPUnit tests are provided. If you want to contribute, make sure to provide unit tests for your contributions.