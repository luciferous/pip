<!doctype html>
<html>
  <head>
    <title>My Guestbook</title>
    <link rel="stylesheet" type="text/css" href="/styles.css"/>
  </head>
  <body>
    <div id="squeeze">
      <h1>Thanks for signing the guestbook!</h1>
      <p><strong>From:</strong> <?php print $from; ?></p>
      <p><?php print $message; ?></p>
    </div>
  </body>
</html>
