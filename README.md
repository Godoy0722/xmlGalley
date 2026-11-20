```
===============================================
=== OJS XML Galley Plugin
=== Version: (see version.xml)
=== Author: MJ Suhonos <mj@robotninja.com>
=== Contributions: Alf Eaton <alf@hubmed.org>
===============================================
```

## About

This plugin renders XML article galleys (`text/xml`, `application/xml`) as
HTML using XSLT. It has built-in support for the NCBI/NLM Journal Publishing
DTD 2.x ("Blue2"), and can use any custom XSL stylesheet that produces an HTML
fragment from the XML.

This is a port of the OJS 2.4.8 plugin to OJS 3.3. PDF generation through
XSL-FO, available in the OJS 2 version, is not part of this port.

## License

This plugin is licensed under the GNU General Public License v2. See the file
docs/COPYING for the complete terms of this license.

## System Requirements

This plugin is compatible with OJS 3.3. It requires an XSLT processor, either:

- PHP's `xsl` and `dom` extensions (recommended), or
- an external command-line processor configured in `config.inc.php`, where
  `%xsl` and `%xml` are replaced by the stylesheet and XML file paths:

```
[cli]
xslt_command = "xsltproc %xsl %xml"
```

## Installation

Place the plugin in `plugins/generic/xmlGalley`. Then, as Journal Manager, go
to Settings > Website > Plugins > Generic Plugins and enable
"XML Galley Plugin".

**Important:** disable the "Lens Galley Plugin" in the same journal. Both
plugins handle XML galleys, and Lens takes precedence when both are enabled.

## Usage

- Upload the XML file as an article galley.
- While uploading, add images and an optional CSS file as dependent files of
  that galley. References to them by file name in the generated HTML (e.g.
  `<img src="figure1.png">`) are rewritten to the correct OJS URLs.
- Without an uploaded CSS file, the theme's HTML galley styles are used.
- Readers clicking the galley see the generated HTML inside the journal's
  galley viewer page.

## Management

The plugin settings can be found at:
Settings > Website > Plugins > Generic Plugins > XML Galley Plugin > Settings

Choose either:

- the built-in NLM stylesheet (default), or
- a custom XSL stylesheet, uploaded as a single `.xsl` file. `xsl:include` and
  `xsl:import` of other files are not supported.

The custom stylesheet is stored in the journal's files directory
(`journals/<id>/xmlGalley/custom.xsl`). Switching back to NLM keeps it; a new
upload replaces it. Saving the settings clears the plugin's cache for the
journal. Journals upgraded from OJS 2 keep using a previously uploaded custom
XSL.

## Notes

- Generated HTML is cached per galley file in the OJS file cache. A new file
  revision is rendered again automatically.
- If the transformation fails (e.g. invalid stylesheet), the raw XML is
  served.
- Malformed XML is repaired by the XML parser where possible, so readers may
  see partial content; check the server log for parser warnings.
- XML that references a remote DTD is resolved over the network, which can be
  slow.
- All XSLT is done in UTF-8.
- A custom XSL can read local server files through `document()`. Only allow
  trusted journal managers to upload one.

## Tests

From the OJS root directory:

```
php lib/pkp/lib/vendor/phpunit/phpunit/phpunit \
  --bootstrap lib/pkp/tests/phpunit-bootstrap.php \
  plugins/generic/xmlGalley/tests/
```
