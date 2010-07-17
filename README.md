Pip, a web server for gentlemen 
===============================

Pip (or Phillip Pirrip) is the main character from the Charles Dickens novel
_Great Expectations_, who leaves his life as a blacksmith's apprentice and
goes to London to become a gentleman.

Pip is also the name of a pure PHP web server.

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

A lot.

To Do
-----

- Change HTTP parser (looking at antlr3 and PECL's HTTP parser)
- Populate PHP superglobals (e.g. $_POST, $_SERVER) when rendering PHP

Contact
-------

Neuman Vong 
