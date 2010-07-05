<!doctype html>
<html>
  <head>
    <title>My Guestbook</title>
    <link rel="stylesheet" type="text/css" href="/styles.css"/>
  </head>
  <body>
    <div id="squeeze">
      <h1>My Guestbook</h1>
      <form action="/" method="POST">
        <div>From</div>
        <div><input type="text" name="from"/></div>
        <div>Message</div>
        <textarea type="text" name="message" rows="10" cols="50"></textarea>
        <div><input type="submit" value="Submit"/></div>
      </form>
    </div>
  </body>
</html>
