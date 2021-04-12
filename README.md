eBook (module for Omeka S)
==========================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[eBook] is a module for [Omeka S] that allows to merge selected resources into
an [ePub] or a [pdf] file for publishing or archiving purpose. An ebook can be
created with the pages of a site too. The aim of such a feature is to have a
static fixed content, an externally usable document or an archival version of
the database. Of course, the ebooks can be displayed in Omeka too.

The ePubs are formatted with version 2.0 or 3.1 and pdf files with version 1.7
or as pdf/a, so it can be read on any device, from the smartphone to the
desktop, and of course by any e-reader that respects the standards.

Conversion into Pdf is not available currently.


Installation
------------

The module uses external libraries to create files, so use the release zip to
install the module, or use and init the source.

* From the zip

Download the last release [Ebook.zip] from the list of releases (the master
does not contain the dependency), and uncompress it in the `modules` directory.

* From the source and for development

If the module was installed from the source, rename the name of the folder of
the module to `Ebook`, go to the root module, and run:

```sh
composer install --no-dev
```


Usage
-----

The ebook can be built for a site or for a list of resources.

For a site, go to its page `navigation` and click `Create ebook`. The ebook is
created with all editorial pages included in the navigation, with the same
structure and order.

For resources, first do a search to list all the items or item sets you want, or
go to the browse page and select a list of item sets, or items. Second, click
the top button `Batch actions`, and `Create ebook from selected` or `Create ebook from all`,
then fill the form.

Ebooks are displayed automatically by Omeka.


TODO
----

- Use a full standard Omeka theme instead of specific templates.
- Manage other types of pages (this will be the case if a full theme is used).
- Include composer in gulpfile.js.
- Pdf management via mpdf.


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitLab.


License
-------

This module is published under the [CeCILL v2.1] license, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

This software is governed by the CeCILL license under French law and abiding by
the rules of distribution of free software. You can use, modify and/ or
redistribute the software under the terms of the CeCILL license as circulated by
CEA, CNRS and INRIA at the following URL "http://www.cecill.info".

As a counterpart to the access to the source code and rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors have only limited liability.

In this respect, the user’s attention is drawn to the risks associated with
loading, using, modifying and/or developing or reproducing the software by the
user in light of its specific status of free software, that may mean that it is
complicated to manipulate, and that also therefore means that it is reserved for
developers and experienced professionals having in-depth computer knowledge.
Users are therefore encouraged to load and test the software’s suitability as
regards their requirements in conditions enabling the security of their systems
and/or data to be ensured and, more generally, to use and operate it in the same
conditions as regards security.

The fact that you are presently reading this means that you have had knowledge
of the CeCILL license and that you accept its terms.

This module uses the following components:

* Fonts from [stixfonts]: see [license]
* ePub css: GPL v2+
* epubjs-reader: BSD-2-Clause


Copyright
---------

* Copyright Daniel Berthereau, 2017-2021 (see [Daniel-KM] on GitLab)
* Stixfonts: see above
* Copyright [Martin Fenner] 2011 (epub css)
* Copyright [Fred Chasen] 2017-2018 ([epubjs-reader])

This module was built first for the French [Université Paris-Diderot].


[eBook]: https://gitlab.com/Daniel-KM/Omeka-S-module-Ebook
[Omeka S]: https://omeka.org/s
[ePub]: http://idpf.org/epub
[pdf]: https://www.adobe.com/devnet/pdf/pdf_reference.html
[Ebook.zip]: https://gitlab.com/Daniel-KM/Omeka-S-module-Ebook/-/releases
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-Ebook/-/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[stixfonts]: http://www.stixfonts.org/
[license]: https://github.com/stipub/stixfonts/raw/master/STIXv2.0.0/docs/STIX_2.0.0_license.pdf
[Martin Fenner]: https://wordpress.org/plugins/epub-export
[Fred Chasen]: https://github.com/fchasen
[epubjs-reader]: https://github.com/futurepress/epubjs-reader
[Université Paris-Diderot]: http://univ-paris8.fr
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
