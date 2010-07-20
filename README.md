Pip, a web server for gentlemen 
===============================

Pip (or Phillip Pirrip) is the main character from the Charles Dickens novel
_Great Expectations_, who leaves his life as a blacksmith's apprentice and
goes to London to become a gentleman.

Pip is also the name of a web development bundle containing the following:

- a pure PHP web server inspired by Unicorn
- a web server interface specification inspired by Rack and WSGI
- a framework inspired by Pylons and Google App Engine

Getting Started With Pip
------------------------

"Hello World" in Pip.

hello.php

    <?php

    require 'pip/http.php';
    require 'pip/webapp.php';

    class HelloWorld extends pip\webapp\RequestHandler {
      function get() {
        $this->response->content_type = 'text/plain';
        fwrite($this->response, 'Hello, world!');
      }
    }

    $app = new pip\webapp\PipApplication(array(
      array('/', 'HelloWorld'),
    ));

    if (!debug_backtrace()) {
      $server = new pip\servers\Http();
      $server->start($app);
    }

Run in a shell.

    $ php hello.php
    [INFO:pip] pip is serving HTTP from localhost on port 5000

See examples/ for more.

Requirements and Installation
-----------------------------

Pip requires PHP 5.3. It uses namespaces, closures and some new
array functions.

See INSTALL for instructions on installing.

Bugs
----

A lot (probably).

To Do
-----

- Change HTTP parser (looking at antlr3 and PECL's HTTP parser)
- Populate PHP superglobals (e.g. $_POST, $_SERVER) when rendering PHP
- Make more PHP-ish
- Convert tests to PHPUnit
- Experiment with using an output buffering callback for chunked transfer

Contact
-------

Neuman Vong 
