<?php

use Leaf\Date;

test('tick accepts timezone parameter', function () {
    $date = tick('2023-01-01', 'America/New_York');
    expect($date->toDateTime()->getTimezone()->getName())->toBe('America/New_York');
});

test('setTimezone changes timezone correctly', function () {
    $date = tick('2023-01-01');
    $date->setTimezone('Europe/London');
    expect($date->toDateTime()->getTimezone()->getName())->toBe('Europe/London');
});

test('timezone affects time representation', function () {
    // Create two dates with the same timestamp but different timezones
    $utcDate = new DateTime('2023-01-01 00:00:00', new DateTimeZone('UTC'));
    $nyDate = new DateTime('2023-01-01 00:00:00', new DateTimeZone('UTC'));
    $nyDate->setTimezone(new DateTimeZone('America/New_York'));
    
    // Create Tick dates from these DateTime objects
    $utcTickDate = tick($utcDate);
    $nyTickDate = tick($nyDate);
    
    // The formatted time should be different
    expect($utcTickDate->format('H:i'))->not->toBe($nyTickDate->format('H:i'));
});

test('can convert DateTime to UTC timezone', function () {
    // Create a DateTime object for a specific time in Los Angeles
    $laDateTime = new DateTime('2023-01-01 04:00:00', new DateTimeZone('America/Los_Angeles'));
    
    // Convert to UTC
    $utcDateTime = clone $laDateTime;
    $utcDateTime->setTimezone(new DateTimeZone('UTC'));
    
    // Verify the conversion is correct
    expect($utcDateTime->getTimezone()->getName())->toBe('UTC');
    expect((int)$utcDateTime->format('H'))->toBe(12); // 4am LA = 12pm UTC
    
    // Verify that the Date class preserves the timezone when creating from DateTime
    $tickDate = tick($laDateTime);
    expect($tickDate->getTimezoneName())->toBe('America/Los_Angeles');
});

test('converting from UTC to specified timezone', function () {
    $utcDate = tick('2023-01-01 12:00:00', 'UTC');
    
    // Manually convert to Los Angeles timezone
    $dateTime = $utcDate->toDateTime();
    $dateTime->setTimezone(new DateTimeZone('America/Los_Angeles'));
    
    expect($dateTime->getTimezone()->getName())->toBe('America/Los_Angeles');
    // UTC is 8 hours ahead of LA, so 12:00 UTC should be 04:00 LA time
    expect((int)$dateTime->format('H'))->toBe(4);
});

test('timezone conversion preserves the exact moment in time', function () {
    $originalDate = tick('2023-01-01 12:00:00', 'UTC');
    $convertedDate = $originalDate->setTimezone('Asia/Tokyo');
    
    // Convert both to Unix timestamps (which are timezone-independent)
    $originalTimestamp = $originalDate->toDateTime()->getTimestamp();
    $convertedTimestamp = $convertedDate->toDateTime()->getTimestamp();
    
    expect($originalTimestamp)->toBe($convertedTimestamp);
});

test('getTimezoneOffset returns offset in minutes', function () {
    // New York is UTC-5 (or UTC-4 during daylight saving)
    $date = tick('2023-01-01', 'America/New_York'); // January is standard time
    
    // Offset should be -300 minutes (-5 hours * 60 minutes)
    expect($date->getTimezoneOffset())->toBe(-300);
});

test('different timezones represent different absolute times', function () {
    // Create DateTime objects with the same local time in different timezones
    $utcDateTime = new DateTime('2023-01-01 12:00:00', new DateTimeZone('UTC'));
    $nyDateTime = new DateTime('2023-01-01 12:00:00', new DateTimeZone('America/New_York'));
    $tokyoDateTime = new DateTime('2023-01-01 12:00:00', new DateTimeZone('Asia/Tokyo'));
    
    // Convert to tick dates
    $utcDate = tick($utcDateTime);
    $nyDate = tick($nyDateTime);
    $tokyoDate = tick($tokyoDateTime);
    
    // The timestamps should be different because the same local time in different
    // timezones represents different moments in absolute time
    $utcTimestamp = $utcDate->toTimestamp();
    $nyTimestamp = $nyDate->toTimestamp();
    $tokyoTimestamp = $tokyoDate->toTimestamp();
    
    expect($utcTimestamp)->not->toBe($nyTimestamp);
    expect($utcTimestamp)->not->toBe($tokyoTimestamp);
    expect($nyTimestamp)->not->toBe($tokyoTimestamp);
});

test('timezone is preserved when manipulating dates', function () {
    $date = tick('2023-01-01', 'Asia/Tokyo');
    $newDate = $date->add(1, 'day');
    
    expect($newDate->toDateTime()->getTimezone()->getName())->toBe('Asia/Tokyo');
});

test('can detect daylight saving time', function () {
    // Most US locations use DST
    $winterDate = tick('2023-01-01', 'America/New_York')->toDateTime();
    $summerDate = tick('2023-07-01', 'America/New_York')->toDateTime();
    
    // Use native DateTime isDST method
    expect($winterDate->format('I'))->toBe('0'); // 0 means not DST
    expect($summerDate->format('I'))->toBe('1'); // 1 means DST is in effect
});

test('timezone abbreviation is correct', function () {
    $winterNY = tick('2023-01-01', 'America/New_York')->toDateTime();
    $summerNY = tick('2023-07-01', 'America/New_York')->toDateTime();
    
    // Use native DateTime format to get timezone abbreviation
    expect($winterNY->format('T'))->toBe('EST');
    expect($summerNY->format('T'))->toBe('EDT');
});
