#!/bin/bash
cd /workspace/wp && wp plugin deactivate fluent-cart fluent-security vfc-login-page >/dev/null 2>&1
cd /workspace/wp; T=wp-content/plugins/film-transfer-toolkit/tests
echo "== core-tests.php"; (cd $T/.. && php tests/core-tests.php 2>&1 | tail -1)
for f in registry-tests.php v130-tests.php v140-tests.php v150-tests.php; do echo "== $f"; wp eval-file $T/$f 2>&1 | grep -E "^FAIL|passed" | tail -6; done
for f in core-tests.js parity.js parity-datarate.js clip-rounding-parity.js; do echo "== $f"; (cd $T && node $f 2>&1 | tail -1); done
echo "== v151-tests.sh"; bash $T/v151-tests.sh /workspace/wp 2>&1 | tail -3
echo "== v152-tests.php"; wp eval-file $T/v152-tests.php 2>&1 | grep -E "^FAIL|passed"; echo "== v153-tests.php"; wp eval-file $T/v153-tests.php 2>&1 | grep -E "^FAIL|passed"; echo "== v154-tests.php (FluentCart off)"; wp eval-file $T/v154-tests.php 2>&1 | grep -E "^FAIL|^SKIP|passed"; echo "== v155-tests.php (FluentCart off)"; wp eval-file $T/v155-tests.php 2>&1 | grep -E "^FAIL|^SKIP|passed"
echo "== v141-tests.sh"; bash $T/v141-tests.sh /workspace/wp 2>&1 | tail -2
cd /workspace/pwt
wp transient delete --all >/dev/null 2>&1
: > /tmp/wp-mail-trap.log; for t in t130 t140 t141 tftt tstorage tfc120 t150 tlook150 t152 t153; do echo "== $t"; timeout 600 node $t.js 2>&1 | grep -E "^FAIL|[0-9]+/[0-9]+( passed)?$|passed, [0-9]+ failed|TimeoutError" | tail -6; done
echo "== twizard"; (cd /workspace/wpfresh && rm -rf wp-content/plugins/film-transfer-toolkit && unzip -qo /workspace/ftt156/film-transfer-toolkit-1.5.6.zip -d wp-content/plugins); bash /workspace/wpfresh-reset.sh >/dev/null 2>&1; timeout 600 node twizard.js 2>&1 | grep -E "^FAIL|passed" | tail -6
echo "== tupgrade152 (1.5.1 -> 1.5.2)"; bash /workspace/upg152-setup.sh 2>&1 | grep -E "CHANGED|TABLE|QUOTES"; timeout 600 node tupgrade152.js 2>&1 | grep -E "^FAIL|passed" | tail -6
cd /workspace/wpfresh && wp plugin deactivate film-transfer-toolkit >/dev/null 2>&1; rm -rf wp-content/plugins/film-transfer-toolkit && unzip -qo /workspace/ftt156/film-transfer-toolkit-1.5.6.zip -d wp-content/plugins; bash /workspace/wpfresh-reset.sh >/dev/null 2>&1
cd /workspace/wp && wp plugin activate fluent-cart >/dev/null 2>&1; echo "== v154-tests.php (FluentCart 2.2.0 on)"; wp eval-file $T/v154-tests.php 2>&1 | grep -E "^FAIL|passed"; echo "== t154 (FluentCart on)"; (cd /workspace/pwt && timeout 600 node t154.js 2>&1 | grep -E "^FAIL|passed" | tail -4); echo "== v155-tests.php (FluentCart on)"; wp eval-file $T/v155-tests.php 2>&1 | grep -E "^FAIL|passed"
wp plugin activate fluent-security vfc-login-page >/dev/null 2>&1; echo "== t155 (FluentCart + FluentAuth 3.0.5 + VFC login page copy)"; (cd /workspace/pwt && timeout 900 node t155.js 2>&1 | grep -E "^FAIL|passed" | tail -6)
wp plugin deactivate fluent-cart fluent-security vfc-login-page >/dev/null 2>&1
echo ALL-DONE-155-SUITES
echo "== v156-tests.php (FluentCart on)"; cd /workspace/wp && wp eval-file $T/v156-tests.php 2>&1 | grep -E "^FAIL|passed"
wp plugin deactivate fluent-security vfc-login-page >/dev/null 2>&1; : > /tmp/wp-mail-trap.log
wp plugin activate fluent-cart >/dev/null 2>&1; echo "== t156 (FluentCart on)"; (cd /workspace/pwt && timeout 600 node t156.js 2>&1 | grep -E "^FAIL|passed" | tail -4)
echo ALL-DONE-156
