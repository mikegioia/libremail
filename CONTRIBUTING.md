# How to contribute

This application is a passion project but all contributes are most certainly welcome!

## Testing

So far, no tests have been written. This is an area that is greatly in need of service. If you're interested, please contact the developers or open an issue.

## Submitting changes

Please send a [GitHub Pull Request](https://github.com/mikegioia/libremail/pull/new/master) with a clear list of what you've done (read more about [pull requests](http://help.github.com/pull-requests/)). Please follow the coding conventions (below) and make sure all of your commits are atomic (one feature per commit).

Always write a clear log message for your commits. One-line messages are fine for small changes, but bigger changes should look like this:

    $ git commit -m "A brief summary of the commit
    > 
    > A paragraph describing what changed and its impact."

## Coding conventions

Start reading the LibreMail code and you'll get the hang of it. We optimize for readability:

  * Indent using four spaces (soft tabs) for PHP, and 2 spaces for CSS.
  * Avoid logic in views.
  * Keep all lines <= 80 characters. This isn't strongly enforced but it should be adhered to as much as possible.
  * ALWAYS put spaces after list items and method parameters (`[1, 2, 3]`, not `[1,2,3]`), around operators (`x += 1`, not `x+=1`), and around hash arrows.
  * This is open source, free as in speech, GPL-only software. Consider the people who will read your code, and make it look nice for them!

Please see the following documents for more information:

  * [README.md](README.md)
  * [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md)
  * [DATA_FORMAT.md](DATA_FORMAT.md)

Thanks,

Mike Gioia
