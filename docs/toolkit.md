---
layout: default
---

**[← Back to Home](index.md)**

# Toolkit

> **Note:** The Toolkit along with all explained tools are planned for a future release and are not yet available in the current version.

MicroUnit provides a set of helpful tools to make writing tests faster, cleaner and more straight foreward.
Listed below you will find all available tools along with their provided functionally.  
Each tool has a section called "Usage Examples" where a full usage example is provided.

---

## ValueBundle

`ValueContext` is a simple container for bundling multiple values, especially useful for returning multiple values from `setUp` or test methods. It offers convenient property-style access and supports marking values as read-only to avoid accidental changes.

You can define and access properties on it, on the fly.

```php

$bundle = new ValueBundle();
$bundle->user = new User('John', 'Smith');

// Then access it at a later point
echo $bundle->user->lastname; // outputs: 'Smith'
```

To define readonly properties use the `set($name, $value, $readonly = false)` method.

```php

$bundle = new ValueBundle();
$bundle->set('user', new User('John', 'Smith'));

// Then access it at a later point
echo $bundle->user->lastname; // outputs: 'Smith'

$bundle->user = new User('Mark', 'Stevens'); // throws RuntimeException
```

---

### Usage Example

```php
use MicroUnit\Toolkit\ValueContext;

class MyTest extends \MicroUnit\TestCase
{
    public function setUp(): ValueContext
    {
        $context = new ValueContext();
        $context->userId = 42;
        $context->token = 'secret-token';
        $context->isAdmin = false;

        // Mark token as read-only to prevent accidental overwrite
        $context->setReadonly('token');

        return $context;
    }

    public function testUserStatus(ValueContext $ctx)
    {
        $this->assertEquals(42, $ctx->userId);
        $this->assertFalse($ctx->isAdmin);

        // This will throw if uncommented:
        // $ctx->token = 'changed';
    }
}
```
