---
layout: default
---

**[← Back to Home](index.md)**

# Toolkit

MicroUnit provides a set of helpful tools to make writing tests faster, cleaner and more straight foreward.
Listed below you will find all available tools along with their provided functionally.  
Each tool has a section called "Usage Examples" where a full usage example is provided.

---

## ValueBundle

A simple container for bundling multiple values, especially useful for returning multiple values from `setUp` or test methods. It offers convenient property-style access and supports marking values as read-only to avoid accidental changes.

You can define and access properties on it, on the fly.

```php

$bundle = new ValueBundle();
$bundle->user = new User('John', 'Smith'); // Or use the 'set' method instead.

// Then access it at a later point
echo $bundle->user->lastname; // outputs: 'Smith'
```

To define readonly properties use the `set($name, $value, $readonly = false)` method and pass `true` for `$readonly`.

```php

$bundle = new ValueBundle();
$bundle->set('user', new User('John', 'Smith'), true);

// Then access it at a later point
echo $bundle->user->lastname; // outputs: 'Smith'

$bundle->user = new User('Mark', 'Stevens'); // throws RuntimeException
```

---

### Usage Example

```php
use MicroUnit\Toolkit\ValueContext;

$tester->setUp(function (): ValueBundle {
    $valueBundle =  new ValueBundle();
    $valueBundle->username = "SomeUsername";
    $valueBundle->set('immutable', 123, true); // define a readonly property
    return $valueBundle;
});

$tester->define("EqualsFails", function (ValueBundle $values) {
    Assert::equals($values->username, 'SomeUsername'); // Is Successfull
    Assert::equals($values->immutable, 123); // Is Successfull

    $values->username = 'OtherUsername'; // Works since it's not readonly
    $values->immutable = 456; // throws a RuntimeException
});

```
