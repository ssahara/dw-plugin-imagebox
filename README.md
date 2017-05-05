# Dokuwiki Plugin Imagebox v2

*Forked from [flammy](https://github.com/flammy/imagebox), FFTiger & myst6re*

Syntax for display an image with a caption, like Wikipedia.org

## Usage

### Basic imagebox
```
[{{:wiki:dokuwiki-128.png?64}}]
[{{:wiki:dokuwiki-128.png?64|caption}}]
[{{:wiki:dokuwiki-128.png?64|title|caption}}]
```

Enclose an image filename with `[{{` and `}}]` to show it in the imagebox.
The "caption" is visible under the image. You can use formatting syntax (bold or italic) in the caption text.
The "title" text will be displayed as a tooltip when you mouse over the image.

### Alignment of imagebox

Add whitespaces to the image filename to set horizontal alignment of the imagebox. 
Imagebox alignment depends on css provided with wrap plgin, therefore you need to install and enable wrap plugin on your DokuWiki installation.

```
[{{:wiki:dokuwiki-128.png?64 |box align left}}]
[{{ :wiki:dokuwiki-128.png?64|box align right}}]
```

### Wide box than image width

The default size of imagebox will just fit the image displayed, 
however you may set larger width for the imagebox than the image to expand space for longer caption text.
This featue requires wrap plugin on your DokuWiki installation.

```
[200px{{:wiki:dokuwiki-128.png?64|title|
image description of which text is longer than image width
}}]
```


