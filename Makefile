# Makefile für tabslFeedback
#
# Erzeugt die minifizierten Frontend-Assets, die der Shop ausliefert. Die
# Quelldateien bleiben daneben liegen und sind die einzige Stelle, an der
# Änderungen vorgenommen werden — nach jeder Änderung "make minify" laufen
# lassen, sonst liefert der Shop weiterhin den alten Stand aus.
#
# Verwendung:
#   make minify
#   make verify

JS_DIR ?= out/src/js
CSS_DIR ?= out/src/css

# Die Werkzeuge sind in package.json auf exakte Versionen festgelegt und werden
# aus node_modules aufgerufen, nicht per "npx --yes" aus der Registry: Die
# minifizierten Dateien liegen im Repository, und eine andere terser-Version
# erzeugt aus derselben Quelle ein anderes Ergebnis — jeder Beitragende würde
# sonst einen Komplett-Diff auf unveränderter Quelle produzieren, und "verify"
# hätte nichts, woran es messen könnte.
NPM ?= npm
RUN = $(NPM) exec --no --

.PHONY: minify verify tools help

tools:
	@[ -d node_modules ] || $(NPM) ci

minify: tools
	@set -e; \
	log=$$(mktemp); trap 'rm -f "$$log"' EXIT INT TERM; \
	for f in $(JS_DIR)/*.js; do \
		case "$$f" in *.min.js) continue;; esac; \
		[ -f "$$f" ] || { echo "Fehler: keine Quelldatei in $(JS_DIR)" >&2; exit 1; }; \
		out="$${f%.js}.min.js"; \
		$(RUN) terser "$$f" -c -m -o "$$out"; \
		[ -s "$$out" ] || { echo "Fehler: $$out fehlt oder ist leer" >&2; exit 1; }; \
		node --check "$$out"; \
		echo "==> $$out"; \
	done; \
	for f in $(CSS_DIR)/*.css; do \
		case "$$f" in *.min.css) continue;; esac; \
		[ -f "$$f" ] || { echo "Fehler: keine Quelldatei in $(CSS_DIR)" >&2; exit 1; }; \
		out="$${f%.css}.min.css"; \
		if ! $(RUN) clean-css-cli -o "$$out" "$$f" >"$$log" 2>&1; then \
			cat "$$log" >&2; \
			echo "Fehler: clean-css abgebrochen ($$f)" >&2; exit 1; \
		fi; \
		[ -s "$$out" ] || { cat "$$log" >&2; echo "Fehler: $$out fehlt oder ist leer" >&2; exit 1; }; \
		grep -E '^(WARNING|ERROR)' "$$log" >&2 || true; \
		echo "==> $$out"; \
	done

# Vergleicht den Inhalt, nicht die Zeitstempel: Git stellt keine mtimes wieder
# her, nach einem Clone liegen Quelle und .min in derselben Sekunde. Ein
# Zeitstempel-Vergleich wäre dort blind und würde eine veraltete Datei als
# aktuell durchwinken. Erzeugt deshalb neu und vergleicht byteweise — das findet
# auch eine von Hand bearbeitete .min-Datei.
verify: tools
	@set -e; \
	tmp=$$(mktemp -d); trap 'rm -rf "$$tmp"' EXIT INT TERM; stale=0; \
	for f in $(JS_DIR)/*.js; do \
		case "$$f" in *.min.js) continue;; esac; \
		[ -f "$$f" ] || { echo "Fehler: keine Quelldatei in $(JS_DIR)" >&2; exit 1; }; \
		min="$${f%.js}.min.js"; \
		$(RUN) terser "$$f" -c -m -o "$$tmp/ref.js"; \
		if [ ! -f "$$min" ]; then echo "fehlt:    $$min" >&2; stale=1; \
		elif ! cmp -s "$$min" "$$tmp/ref.js"; then echo "abweichend: $$min" >&2; stale=1; \
		else echo "aktuell:  $$min"; fi; \
	done; \
	for f in $(CSS_DIR)/*.css; do \
		case "$$f" in *.min.css) continue;; esac; \
		[ -f "$$f" ] || { echo "Fehler: keine Quelldatei in $(CSS_DIR)" >&2; exit 1; }; \
		min="$${f%.css}.min.css"; \
		if ! $(RUN) clean-css-cli -o "$$tmp/ref.css" "$$f" >"$$tmp/log" 2>&1; then \
			cat "$$tmp/log" >&2; echo "Fehler: clean-css abgebrochen ($$f)" >&2; exit 1; \
		fi; \
		if [ ! -f "$$min" ]; then echo "fehlt:    $$min" >&2; stale=1; \
		elif ! cmp -s "$$min" "$$tmp/ref.css"; then echo "abweichend: $$min" >&2; stale=1; \
		else echo "aktuell:  $$min"; fi; \
	done; \
	if [ $$stale -ne 0 ]; then echo "==> 'make minify' ausführen" >&2; exit 1; fi

help:
	@echo "make minify   *.min.js/*.min.css aus den Quelldateien erzeugen"
	@echo "make verify   prüfen, ob die minifizierten Assets zum Quellstand passen"
	@echo "make tools    Build-Werkzeuge in den festgelegten Versionen installieren"
