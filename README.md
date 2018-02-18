<h1 align="center">
  PHP .ini parser
</h1>
<p align="center">
    Ini Parser is a simple parser for complex INI files, providing a number of extra syntactic features to the built-in INI parsing functions, including section inheritance, property nesting, and array literals.
</p>

<hr />

[![Build Status][build-badge]][build]
[![Code Cov][codecov-badge]][codecov]
[![Scrutinizer][scrutinizer-badge]][scrutinizer]
[![downloads][downloads-badge]][downloads]
[![MIT License][license-badge]][license]

[![All Contributors](https://img.shields.io/badge/all_contributors-2-orange.svg?style=flat-square)](#contributors)
[![PRs Welcome][prs-badge]][prs]
[![Code of Conduct][coc-badge]][coc]
[![Watch on GitHub][github-watch-badge]][github-watch]
[![Star on GitHub][github-star-badge]][github-star]
[![Tweet][twitter-badge]][twitter]

## Installing

Add `me-io/php-ini-parser` following inside your `composer.json` file like this:

```json
{
    "require": {
        "me-io/php-ini-parser": "^1"
    }
}
```

Then inside your terminal run the following command to install the dependencies:

```bash
composer install
```

## Example

Standard INI files look like this:

```ini
key = value
another_key = another value

[section_name]
a_sub_key = yet another value
```

And when parsed with PHP's built-in `parse_ini_string()` or `parse_ini_file()`, looks like:

```php
[
    'key' => 'value',
    'another_key' => 'another value',
    'section_name' => [
        'a_sub_key' => 'yet another value'
    ]
]
```

This is great when you just want a simple configuration file, but here is a super-charged INI file that you might find in the wild:

```ini
environment = testing

[testing]
debug = true
database.connection = "mysql:host=127.0.0.1"
database.name = test
database.username = 
database.password =
secrets = [1,2,3]

[staging : testing]
database.name = stage
database.username = staging
database.password = 12345

[production : staging]
debug = false;
database.name = production
database.username = root
```

And when parsed with \Ini\Parser:

```ini
$parser = new \Ini\Parser('sample.ini');
$config = $parser->parse();
```

You get the following structure:

```php
[
    'environment' => 'testing',
    'testing' => [
        'debug' => '1',
        'database' => [
            'connection' => 'mysql:host=127.0.0.1',
            'name' => 'test',
            'username' => '',
            'password' => ''
        ],
        'secrets' => ['1','2','3']
    ],
    'staging' => [
        'debug' => '1',
        'database' => [
            'connection' => 'mysql:host=127.0.0.1',
            'name' => 'stage',
            'username' => 'staging',
            'password' => '12345'
        ],
       'secrets' => ['1','2','3']
    ],
    'production' => [
        'debug' => '',
        'database' => [
            'connection' => 'mysql:host=127.0.0.1',
            'name' => 'production',
            'username' => 'root',
            'password' => '12345'
        ],
        'secrets' => ['1','2','3']
    ]
]
```

## Supported Features

### Array Literals

You can directly create arrays using the syntax `[a, b, c]` on the right hand side of an assignment. For example:

```ini
colors = [blue, green, red]
```

**NOTE:** At the moment, quoted strings inside array literals have undefined behavior.

### Dictionaries and complex structures

Besides arrays, you can create dictionaries and more complex structures using JSON syntax. For example, you can use:

```json
people = '{
    "boss": {
        "name": "John", 
        "age": 42 
    }, 
    "staff": [
        {
            "name": "Mark",
            "age": 35 
        }, 
        {
            "name": "Bill", 
            "age": 44 
        }
    ] 
}'
```

This turns into an array like:

```php
[
    'boss' => [
        'name' => 'John',
        'age' => 42
    ],
    'staff' => [
        [
            'name' => 'Mark',
            'age' => 35,
        ],
        [
            'name' => 'Bill',
            'age' => 44,
        ],
    ],
]
```

> **NOTE:**  Remember to wrap the JSON strings in single quotes for a correct analysis. The JSON names must be enclosed in double quotes and trailing commas are not allowed.

### Property Nesting

Ini Parser allows you to treat properties as associative arrays:

```ini
person.age = 42
person.name.first = John
person.name.last = Doe
```

This turns into an array like:

```php
[
    'person' => [
        'age' => 42,
        'name' => [
            'first' => 'John',
            'last' => 'Doe'
        ]
    ]
]
```

### Section Inheritance

Keeping to the DRY principle, Ini Parser allows you to "inherit" from other sections (similar to OOP inheritance), meaning you don't have to continually re-define the same properties over and over again. As you can see in the example above, "production" inherits from "staging", which in turn inherits from "testing".

You can even inherit from multiple parents, as in `[child : p1 : p2 : p3]`. The properties of each parent are merged into the child from left to right, so that the properties in `p1` are overridden by those in `p2`, then by `p3`, then by those in `child` on top of that.

During the inheritance process, if a key ends in a `+`, the merge behavior changes from overwriting the parent value to prepending the parent value (or appending the child value - same thing). So the example file

```ini
[parent]
arr = [a,b,c]
val = foo

[child : parent]
arr += [x,y,z]
val += bar
```

would be parsed into the following:

```php
[
    'parent' => [
        'arr' => ['a','b','c'],
        'val' => 'foo'
    ],
    'child' => [
        'arr' => ['a','b','c','x','y','z'],
        'val' => 'foobar'
    ]
]
```

> *If you can think of a more useful operation than concatenation for non-array types, please open an issue*

Finally, it is possible to inherit from the special `^` section, representing the top-level or global properties:

```init
foo = bar

[sect : ^]
```

Parses to:

```php
[
    'foo' => 'bar',
    'sect' => [
        'foo' => 'bar'
    ]
]
```

### ArrayObject

As an added bonus, Ini Parser also allows you to access the values OO-style:

```php
echo $config->production->database->connection; // output: mysql:host=127.0.0.1
echo $config->staging->debug; // output: 1
```

## Contributors

A huge thanks to all of our contributors:

<!-- ALL-CONTRIBUTORS-LIST:START - Do not remove or modify this section -->
<!-- prettier-ignore -->
| [<img src="https://avatars0.githubusercontent.com/u/45731?v=3" width="100px;"/><br /><sub><b>Mohamed Meabed</b></sub>](https://github.com/Meabed)<br />[💻](https://github.com//php-ini-parser/commits?author=Meabed "Code") [📢](#talk-Meabed "Talks") | [<img src="https://avatars2.githubusercontent.com/u/16267321?v=3" width="100px;"/><br /><sub><b>Zeeshan Ahmad</b></sub>](https://github.com/zeeshanu)<br />[💻](https://github.com//php-ini-parser/commits?author=zeeshanu "Code") [🐛](https://github.com//php-ini-parser/issues?q=author%3Azeeshanu "Bug reports") [⚠️](https://github.com//php-ini-parser/commits?author=zeeshanu "Tests") [📖](https://github.com//php-ini-parser/commits?author=zeeshanu "Documentation") |
| :---: | :---: |
<!-- ALL-CONTRIBUTORS-LIST:END -->

## License

The code is available under the [MIT license](LICENSE.md).

[build-badge]: https://img.shields.io/travis/me-io/php-ini-parser.svg?style=flat-square
[build]: https://travis-ci.org/me-io/php-ini-parser
[downloads-badge]: https://img.shields.io/packagist/dm/me-io/php-ini-parser.svg?style=flat-square
[downloads]: https://packagist.org/packages/me-io/php-ini-parser/stats
[license-badge]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[license]: https://github.com/me-io/php-ini-parser/blob/master/LICENSE.md
[prs-badge]: https://img.shields.io/badge/PRs-welcome-brightgreen.svg?style=flat-square
[prs]: http://makeapullrequest.com
[coc-badge]: https://img.shields.io/badge/code%20of-conduct-ff69b4.svg?style=flat-square
[coc]: https://github.com/me-io/php-ini-parser/blob/master/CODE_OF_CONDUCT.md
[github-watch-badge]: https://img.shields.io/github/watchers/me-io/php-ini-parser.svg?style=social
[github-watch]: https://github.com/me-io/php-ini-parser/watchers
[github-star-badge]: https://img.shields.io/github/stars/me-io/php-ini-parser.svg?style=social
[github-star]: https://github.com/me-io/php-ini-parser/stargazers
[twitter]: https://twitter.com/intent/tweet?text=Check%20out%20php-ini-parser!%20https://github.com/me-io/php-ini-parser%20%F0%9F%91%8D
[twitter-badge]: https://img.shields.io/twitter/url/https/github.com/me-io/php-ini-parser.svg?style=social
[codecov-badge]: https://codecov.io/gh/me-io/php-ini-parser/branch/master/graph/badge.svg
[codecov]: https://codecov.io/gh/me-io/php-ini-parser
[scrutinizer-badge]: https://scrutinizer-ci.com/g/me-io/php-ini-parser/badges/quality-score.png?b=master
[scrutinizer]: https://scrutinizer-ci.com/g/me-io/php-ini-parser/?branch=master
