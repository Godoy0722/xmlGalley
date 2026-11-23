# xmlGalley examples

Sample files for trying the plugin by hand or in automated browser runs.
Each file's header comment says what it is for and what should happen.

## xml/ — galley files (upload as the XML galley)

| File | Covers | Expected result |
|---|---|---|
| `full-article.xml` | Every element the built-in NLM stylesheet renders: journal and article metadata, authors with affiliations, corresponding author, reviewer, author notes, dates, permissions, abstract and translated abstract, keywords, nested sections, inline styles, lists, definition lists, quotes, figures, tables, MathML and TeX formulas, preformatted code, boxed text, speech, verse, statements, acknowledgements, appendix, glossary, footnotes, notes, and every citation type | Full HTML article. Images and links point to the dependent-file URLs. `missing.png` stays unchanged (broken image) |
| `minimal-article.xml` | Smallest article; no dependent files | Title, author, one paragraph. Theme galley styles are used (no `style.css`) |
| `multilingual-article.xml` | Accented, Greek, Cyrillic, CJK, RTL text, emoji, entities, escaped markup | All characters show correctly (UTF-8). `<script>` is shown as text and does not run |
| `dtd-article.xml` | DOCTYPE with a remote NLM DTD | Renders; the first view is slow while PHP fetches the DTD (or fails when offline) |
| `broken-article.xml` | XML that is not well-formed | Repaired, partial HTML. No fatal error |
| `unknown-vocabulary.xml` | Well-formed XML that is not NLM/JATS | Near-empty output, or raw XML if the output is empty. No fatal error |

## dependent-files/ — upload with `full-article.xml`

Upload these in the galley's **Dependent Files** grid.

| File | Referenced in `full-article.xml` as | Checks |
|---|---|---|
| `figure1.png` (red) | `figure1.png` | Plain name |
| `figure 2.png` (green) | `figure 2.png` and `figure%202.png` | Name with a space, raw and URL-encoded |
| `figure3.png` (blue) | `images/figure3.png` | Folder prefix is ignored |
| `appendix.pdf` | `<inline-supplementary-material>` | `href` rewrite |
| `data.csv` | `<supplementary-material>` | `href` rewrite |
| `style.css` | (not referenced) | Linked in `<head>`: pale yellow background replaces the theme styles |

## xsl/ — custom stylesheets (upload in the plugin Settings)

| File | Expected result |
|---|---|
| `custom-basic.xsl` | Accepted. Galley shows `CUSTOM: <title>` |
| `custom-v2.xsl` | Accepted; replaces the stored file. Galley shows `CUSTOM 2: <title>` |
| `CUSTOM-UPPERCASE.XSL` | Accepted (extension check ignores case). Galley shows `CUSTOM UPPERCASE: <title>` |
| `custom-full.xsl` | Accepted. Full custom rendering marked `CUSTOM FULL`; its `img src`, `a href` and `object data` are rewritten to dependent-file URLs |
| `custom-include.xsl` | Accepted on upload, fails at render time (the included sibling file is not stored). Raw XML is served |
| `runtime-error.xsl` | Accepted on upload, stops itself at render time (`xsl:message terminate`). Raw XML is served |
| `empty-output.xsl` | Accepted on upload; the output is empty. Raw XML is served |
| `broken.xsl` | Rejected: "not valid". Nothing saved |
| `not-a-stylesheet.xsl` | Rejected: "not valid" (no XSLT root). Nothing saved |
| `custom-basic.txt` | Rejected: "not valid" (wrong extension). Nothing saved |

After any of the "Raw XML is served" cases, switch back to **Built-in NLM** or
upload `custom-basic.xsl`.

## Mimetypes

The plugin handles galleys whose file type is `text/xml` or `application/xml`.
OJS sets the type when the file is uploaded. Which of the two an `.xml` file
gets depends on the server, so check the stored type
(`submission_files` / `files.mimetype`) when testing both.
