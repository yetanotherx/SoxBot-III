<?php

$testlist = array(
		'/\[\[(Link title|File:Example\.jpg|Media:Example\.ogg)\]\]/' => -5,
		'/\'\'\'Bold text\'\'\'/'			                          => -2,
		'/\'\'Italic text\'\'/'			                              => -2,
		'/\'\'\'\'\'Bold text\'\'\'\'\'/'			                  => -5,
		'/\'\'\'\'\'Italic text\'\'\'\'\'/'			                  => -5,
		'/\[http:\/\/www\.example\.com link title\]/'	              => -8,
		'/== Headline text ==/'				                          => -12,
		'/\<math\>Insert formula here\<\/math\>/'			          => -20,
		'/\<nowiki\>Insert non-formatted text here\<\/nowiki\>/'	  => -20,
		'/#REDIRECT \[\[Insert text\]\]/'			                  => -10,
		'/\<s\>Strike-through text\<\/s\>/'			                  => -3,
		'/\<sup\>Superscript text\<\/sup\>/'		                  => -3,
		'/\<sub\>Subscript text\<\/sub\>/'			                  => -3,
		'/\<small\>Small Text\<\/small\>/'				              => -3,
		'/\<!-- Comment --\>/'		                                  => -15,
		'/\<gallery\>
(Image|File):Example.jpg\|Caption1
(Image|File):Example.jpg\|Caption2
\<\/gallery\>/m'													  => -5,
		'/\<blockquote\>
Block quote
\<\/blockquote\>/m'													  => -5,
		'/'.preg_quote('{| class="wikitable" border="1"
|-
! header 1
! header 2
! header 3
|-
| row 1, cell 1
| row 1, cell 2
| row 1, cell 3
|-
| row 2, cell 1
| row 2, cell 2
| row 2, cell 3
|}').'/m'														      => -5,
		'/\<ref\>Insert footnote text here\<\/ref\>/'				  => -5,
		'/(ghjk|asdf|zxcv)/i'			                              => -8,
		'/--\[\[Special:Contributions\/.*\|.*\]\]/'                   => -5
	);
