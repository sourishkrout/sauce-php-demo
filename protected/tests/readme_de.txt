In dieses Verzeichnis befinden sich funktionale sowie Unit-Tests fuer unser Blog demo. 

 - fixtures: beinhaltet Testdaten fuer die relevanten Datenbanktabellen.
   Jeder Datei wird verwendet um die zugehoerige Datenbanktabelle mit Testdaten zu befuellen.
   Datei- und Tabellenname stimmen ueberein.

 - functional: beinhaltet die funktionalen Tests.

 - unit: beinhaltet die Unit-Tests

 - report: contains any coverage reports.


Um die Tests ausfuehren zu koennen muessen folgende Voraussetzungen erfuellt sein: 

 - PHPUnit 3.3 oder neuere Version
 - Selenium RC oder neuere Version


Abhaenging vom Yii Release, muss moeglicherweise die Datei "WebTestCase.php"
angepasst werden, so dass die "TEST_BASE_URL" Konstante auf die jeweils passende
Basis-URL zeigt.
Zusaetzlich kann mittels der Datei phpunit.xml die zu testenden Browser fuer
die funktionalen Tests konfiguriert werden.

Genau Details zur Ausfuehrung der Tests koennen der PHPUnit Dokumentation entnommen werden.
Nachfolgend ein paar Beispiele:

 - Ausfuehren aller Tests unterhalb des "unit" Verzeichnis mit ausfuehrlichen Informationen:

	phpunit --verbose unit

 - Ausfuerhen aller Tests unterhalb des "functional" Verzeichnis (Selenium RC laeuft bereits):

	phpunit functional

 - Ausfuehren einer bestimmten Testklasse:

	phpunit functional/PostTest.php


*DISCLAIMER* Die aufgezeigten Testsbeispiele decken lediglich einen zur Demonstration
hilfreichen Teil der Bloganwendung ab. Eine komplette Testabdeckung wuerde den Rahmen dieser
Demonstration sprengen.
