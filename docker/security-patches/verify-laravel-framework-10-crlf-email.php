<?php

declare(strict_types=1);

require '/fleetbase/api/vendor/autoload.php';

$badAddress = "\"foo\r\nBcc: victim@example.com\"@example.com";
$translator = new Illuminate\Translation\Translator(
    new Illuminate\Translation\ArrayLoader(),
    'en'
);
$validator = new Illuminate\Validation\Factory($translator);

if ($validator->make(['email' => $badAddress], ['email' => 'email'])->passes()) {
    fwrite(STDERR, "Laravel email validation accepted a CRLF address.\n");
    exit(1);
}

try {
    new Illuminate\Mail\Mailables\Address($badAddress);
    fwrite(STDERR, "Laravel mailable address accepted a CRLF address.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

try {
    (new Illuminate\Mail\Message(new Symfony\Component\Mime\Email()))->to($badAddress);
    fwrite(STDERR, "Laravel mail message accepted a CRLF address.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

$normalAddress = 'customer@example.com';

if (! $validator->make(['email' => $normalAddress], ['email' => 'email'])->passes()) {
    fwrite(STDERR, "Laravel email validation rejected a normal address.\n");
    exit(1);
}

(new Illuminate\Mail\Mailables\Address($normalAddress));
(new Illuminate\Mail\Message(new Symfony\Component\Mime\Email()))->to($normalAddress);

fwrite(STDOUT, "Laravel CRLF email security contract passed.\n");
