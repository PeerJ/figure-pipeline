<?xml version="1.0" encoding="UTF-8"?>
<stylesheet version="1.0" exclude-result-prefixes="str"
  xmlns="http://www.w3.org/1999/XSL/Transform"
  xmlns:str="http://exslt.org/strings">

  <output encoding="utf-8" indent="yes"/>

  <param name="article-doi"/>
  <param name="authors"/>
  <param name="subjects"/>
  <param name="journal-title"/>
  <param name="license-url"/>

  <param name="article-url" select="concat('http://dx.doi.org/', $article-doi)"/>

  <template match="/">
    <apply-templates match="fig"/>
  </template>

  <template match="fig">
    <variable name="doi" select="object-id[@pub-id-type='doi']"/>
    <variable name="url" select="concat('http://dx.doi.org/', $doi)"/>

    <x:xmpmeta xmlns:x="adobe:ns:meta/">
      <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
        <rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">
          <dc:format>image/png</dc:format>

          <dc:identifier>
            <value-of select="$url"/>
          </dc:identifier>

          <dc:title>
            <rdf:Alt>
              <rdf:li>
                <value-of select="caption/title"/><!-- text -->
              </rdf:li>
            </rdf:Alt>
          </dc:title>

          <dc:creator>
            <rdf:Seq>
              <for-each select="str:tokenize($authors, ';')">
                <rdf:li>
                  <value-of select="."/>
                </rdf:li>
              </for-each>
            </rdf:Seq>
          </dc:creator>

          <dc:subject>
            <rdf:Bag>
              <for-each select="str:tokenize($subjects, ';')">
                <rdf:li>
                  <value-of select="."/>
                </rdf:li>
              </for-each>
            </rdf:Bag>
          </dc:subject>

          <dc:description>
            <rdf:Alt>
              <rdf:li>
                <value-of select="label"/>
                <text>: </text>
                <value-of select="caption/title"/>
                <text> </text>
                <value-of select="caption/p"/><!-- text -->
                <text> Cite this object using this DOI: </text>
                <value-of select="$doi"/>
              </rdf:li>
            </rdf:Alt>
          </dc:description>

          <dc:publisher>
            <rdf:Bag>
              <rdf:li>
                <value-of select="$journal-title"/>
              </rdf:li>
            </rdf:Bag>
          </dc:publisher>

          <dc:rights>
            <rdf:Alt>
              <rdf:li>
                <value-of select="$license-url"/>
              </rdf:li>
            </rdf:Alt>
          </dc:rights>
        </rdf:Description>
      </rdf:RDF>
    </x:xmpmeta>
  </template>
</stylesheet>
