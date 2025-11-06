<?php
/**
 * Plugin Name: MI Helper Reports (Lite-safe)
 * Description: Admin-only helper to export MonsterInsights report data using MI’s internal API client.
 * Version: 0.2.2
 * Author: 
 */

if ( ! defined('ABSPATH') ) exit;

/** Detect MI Lite or Pro and ensure API client is available */
function mihr_is_mi_active() {
    $has_core = class_exists('MonsterInsights_Lite')
             || class_exists('MonsterInsights')
             || defined('MONSTERINSIGHTS_VERSION')
             || defined('MONSTERINSIGHTS_LITE_VERSION');

    if ( $has_core && ! class_exists('MonsterInsights_API_Request') ) {
        if ( defined('MONSTERINSIGHTS_PLUGIN_DIR') ) {
            $candidate = trailingslashit(MONSTERINSIGHTS_PLUGIN_DIR) . 'includes/api-request.php';
            if ( file_exists($candidate) ) {
                require_once $candidate;
            }
        }
    }
    return $has_core && class_exists('MonsterInsights_API_Request');
}

/** Add admin page under Tools */
add_action('admin_menu', function () {
    if ( ! is_admin() || ! current_user_can('manage_options') ) return;

    add_submenu_page(
        'tools.php',
        'Helper Reports',
        'Helper Reports',
        'manage_options',         // admins only
        'mi-helper-reports',
        'mihr_render_page'
    );
}, 99);

/** Render page */
function mihr_render_page() {
    if ( ! current_user_can('manage_options') ) wp_die('You do not have permission to view this page.');
    $nonce     = wp_create_nonce('mihr');
    $mi_active = mihr_is_mi_active();

    // GA4-ish metric keys MI commonly returns in Lite "overview" (others may be ignored gracefully)
    $metric_defs = array(
        'sessions'                => 'Sessions',
        'users'                   => 'Users',
        'newUsers'                => 'New users',
        'pageviews'               => 'Pageviews',
        'screenPageViews'         => 'Screen/Page views',
        'engagedSessions'         => 'Engaged sessions',
        'averageSessionDuration'  => 'Avg. session duration',
        'bounceRate'              => 'Bounce rate',
    );
    ?>
    <div class="wrap">
      <h1>MonsterInsights Helper Reports</h1>
      <?php if ( ! $mi_active ): ?>
        <div class="notice notice-warning"><p>MonsterInsights Lite/Pro is not active. Please install/activate it first.</p></div>
      <?php endif; ?>

      <form id="mihr-form" onsubmit="return false;" <?php echo $mi_active ? '' : 'style="opacity:.6;pointer-events:none"'; ?>>
        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><label for="mihr-start">Start date</label></th>
            <td><input type="date" id="mihr-start" required></td>
          </tr>
          <tr>
            <th scope="row"><label for="mihr-end">End date</label></th>
            <td><input type="date" id="mihr-end" required></td>
          </tr>
          <tr>
            <th scope="row"><label for="mihr-report">Report</label></th>
            <td>
              <select id="mihr-report">
                <option value="overview">overview</option>
              </select>
              <p class="description">Lite supports <code>overview</code>. (Other routes may 404 in Lite.)</p>
            </td>
          </tr>
          <tr>
            <th scope="row">Included metrics</th>
            <td>
              <p style="margin:.5em 0">
                <label><input type="checkbox" id="mihr-select-all"> <em>Select all</em></label>
              </p>
              <fieldset id="mihr-metrics-boxes">
                <?php foreach ( $metric_defs as $key => $label ): ?>
                  <label style="display:inline-block;margin:0 16px 8px 0;">
                    <input type="checkbox" class="mihr-metric" value="<?php echo esc_attr($key); ?>">
                    <?php echo esc_html($label); ?>
                  </label>
                <?php endforeach; ?>
              </fieldset>
              <p class="description">Check one or more. Lite may ignore premium-only metrics; that’s OK.</p>
            </td>
          </tr>
        </table>
        <p>
          <button class="button" id="mihr-preview">Preview</button>
          <button class="button button-primary" id="mihr-run">Download CSV</button>
        </p>
      </form>

      <div id="mihr-status" style="margin-top:10px;"></div>
      <div id="mihr-results" style="margin-top:20px;"></div>
    </div>

<script>
(function(){
  const previewBtn = document.getElementById('mihr-preview');
  const runBtn     = document.getElementById('mihr-run'); // will download ALL sections
  const statusEl   = document.getElementById('mihr-status');
  const resultsEl  = document.getElementById('mihr-results');
  const selectAll  = document.getElementById('mihr-select-all');
  const metricBoxes= Array.from(document.querySelectorAll('.mihr-metric'));

  function esc(s){ return (''+s).replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }
  function toNumber(x){ const n = typeof x === 'string' ? x.replace(/,/g,'').trim() : x; const f = parseFloat(n); return isFinite(f) ? f : null; }
  function toISODate(unix){ if (!unix) return ''; const d=new Date(unix*1000); const m=String(d.getMonth()+1).padStart(2,'0'); const day=String(d.getDate()).padStart(2,'0'); return `${d.getFullYear()}-${m}-${day}`; }

  if (selectAll) {
    selectAll.addEventListener('change', () => {
      metricBoxes.forEach(cb => cb.checked = selectAll.checked);
    });
  }

  function makeCSV(rows, cols){
    const head = cols.join(',');
    const body = rows.map(r => cols.map(c=>{
      const v = (r[c] ?? '').toString().replace(/"/g,'""');
      return /[",\n]/.test(v) ? `"${v}"` : v;
    }).join(',')).join('\n');
    return head + '\n' + body;
  }
  function colsFromRows(rows){
    return Array.from(rows.reduce((set, r)=>{ Object.keys(r).forEach(k=>set.add(k)); return set; }, new Set()));
  }
  function downloadCSV(text, name){
    const blob = new Blob([text], {type:'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.download = name;
    document.body.appendChild(a); a.click(); URL.revokeObjectURL(url); a.remove();
  }
  function renderTable(title, rows){
    if (!rows || !rows.length) return { html:'', csv:'' };
    const cols = colsFromRows(rows);
    const html = [
      `<h3 style="margin-top:1.2em;">${esc(title)}</h3>`,
      '<table class="widefat striped"><thead><tr>',
      cols.map(c=>`<th>${esc(c)}</th>`).join(''),
      '</tr></thead><tbody>',
      rows.map(r=>'<tr>'+cols.map(c=>`<td>${esc(r[c] ?? '')}</td>`).join('')+'</tr>').join(''),
      '</tbody></table>'
    ].join('');
    const csv = makeCSV(rows, cols);
    return { html, csv };
  }

  function buildSections(d){
    const sections = [];

    // 1) SUMMARY (infobox)
    if (d.infobox){
      const ib = d.infobox;
      const sessions    = ib.sessions?.value ?? '';
      const pageviews   = ib.pageviews?.value ?? '';
      const totalUsers  = ib.totalusers?.value ?? '';
      const newUsers    = ib.new_users?.value ?? '';
      const duration    = ib.duration?.value ?? '';
      const bounceRate  = ib.bounce_rate?.value ?? '';
      let pvu = '';
      const pvNum = toNumber(pageviews), tuNum = toNumber(totalUsers);
      if (pvNum != null && tuNum != null && tuNum > 0) pvu = (pvNum / tuNum).toFixed(2);
      const row = [{
        range_start: ib.current?.startDate || '',
        range_end:   ib.current?.endDate   || '',
        sessions, pageviews, total_users: totalUsers, pageviews_per_user: pvu,
        session_duration: duration, bounce_rate: bounceRate, new_users: newUsers
      }];
      sections.push({ key:'summary', title:'Summary (Overview)', rows: row });
    }

    // 2) DAILY time-series (overviewgraph: sessions + pageviews)
    if (d.overviewgraph){
      const og = d.overviewgraph;
      const labels = Array.isArray(og.labels) ? og.labels : [];
      const ts     = Array.isArray(og.timestamps) ? og.timestamps : [];
      const s      = (og.sessions?.datapoints && Array.isArray(og.sessions.datapoints)) ? og.sessions.datapoints : [];
      const pv     = (og.pageviews?.datapoints && Array.isArray(og.pageviews.datapoints)) ? og.pageviews.datapoints : [];
      const n = Math.max(labels.length, ts.length, s.length, pv.length);
      const rows = [];
      for (let i=0;i<n;i++){
        const dateLabel = (labels[i] || '').trim();
        const iso = ts[i] ? toISODate(ts[i]) : '';
        rows.push({ date: iso || dateLabel || `Day ${i+1}`, sessions: s[i] ?? '', pageviews: pv[i] ?? '' });
      }
      if (rows.length) sections.push({ key:'daily', title:'Daily (Sessions & Pageviews)', rows });
    }

    // 3) New vs Returning
    if (d.newvsreturn && (d.newvsreturn.new != null || d.newvsreturn.returning != null)){
      const rows = [];
      if (d.newvsreturn.new != null)       rows.push({ type:'new', value: d.newvsreturn.new });
      if (d.newvsreturn.returning != null) rows.push({ type:'returning', value: d.newvsreturn.returning });
      if (rows.length) sections.push({ key:'newvsreturn', title:'New vs Returning', rows });
    }

    // 4) Devices (percent split)
    if (d.devices){
      const rows = [];
      Object.keys(d.devices).forEach(k => rows.push({ device: k, percent: d.devices[k] }));
      if (rows.length) sections.push({ key:'devices', title:'Devices', rows });
    }

    // 5) Countries
    if (Array.isArray(d.countries) && d.countries.length){
      const rows = d.countries.map(c => ({ iso: c.iso || '', sessions: c.sessions ?? '' }));
      sections.push({ key:'countries', title:'Countries', rows });
    }

    // 6) Referrals
    if (Array.isArray(d.referrals) && d.referrals.length){
      const rows = d.referrals.map(r => ({ url: r.url || '', sessions: r.sessions ?? '' }));
      sections.push({ key:'referrals', title:'Referrals', rows });
    }

    // 7) Top Pages
    if (Array.isArray(d.toppages) && d.toppages.length){
      const rows = d.toppages.map(p => ({
        url: p.url || '', title: p.title || '', hostname: p.hostname || '', sessions: p.sessions ?? ''
      }));
      sections.push({ key:'toppages', title:'Top Pages', rows });
    }

    // 8) Ranges
    if (d.reportcurrentrange){
      const r = d.reportcurrentrange;
      sections.push({ key:'current_range', title:'Current Range', rows:[{ start: r.startDate || '', end: r.endDate || '' }] });
    }
    if (d.reportprevrange){
      const r = d.reportprevrange;
      sections.push({ key:'previous_range', title:'Previous Range', rows:[{ start: r.startDate || '', end: r.endDate || '' }] });
    }

    return sections;
  }

  function renderSections(sections){
    const parts = [];
    const csvMap = {}; // key -> csv
    sections.forEach(sec => {
      const { html, csv } = renderTable(sec.title, sec.rows);
      if (html){
        const btnId = `mihr-dl-${sec.key}`;
        parts.push(html + `<p><button type="button" class="button" id="${btnId}">Download ${esc(sec.title)} CSV</button></p>`);
        csvMap[sec.key] = csv;
        // attach click after inject
        setTimeout(() => {
          const el = document.getElementById(btnId);
          if (el) el.addEventListener('click', ()=> downloadCSV(csv, `mi-${sec.key}.csv`));
        }, 0);
      }
    });

    // Combined CSV: title lines + each CSV + blank line
    const combined = sections
      .filter(sec => csvMap[sec.key])
      .map(sec => {
        const titleLine = `# ${sec.title}`;
        return titleLine + '\n' + csvMap[sec.key];
      })
      .join('\n\n');

    return { html: parts.join(''), combinedCSV: combined };
  }

  async function doFetch(downloadAll){
    statusEl.textContent = 'Fetching…';
    resultsEl.innerHTML = '';

    const selectedMetrics = metricBoxes.filter(cb => cb.checked).map(cb => cb.value);
    const payload = new URLSearchParams();
    payload.set('action','mihr_fetch');
    payload.set('nonce','<?php echo esc_js($nonce); ?>');
    payload.set('start', document.getElementById('mihr-start').value || '');
    payload.set('end',   document.getElementById('mihr-end').value   || '');
    payload.set('report',document.getElementById('mihr-report').value || 'overview');
    if (selectedMetrics.length) payload.set('included_metrics', selectedMetrics.join(','));

    const res = await fetch(ajaxurl, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: payload.toString() });
    let data; try { data = await res.json(); } catch(e){ data = { success:false, data:'Invalid JSON response' }; }

    if (!data || !data.success) {
      statusEl.innerHTML = '<span style="color:#b00;">' + esc((data && data.data) || 'Request failed') + '</span>';
      return;
    }
    statusEl.textContent = 'OK';

    const d = data.data || {};
    const sections = buildSections(d);

    if (!sections.length){
      resultsEl.innerHTML = '<pre style="max-height:480px;overflow:auto;">' + esc(JSON.stringify(d, null, 2)) + '</pre>';
      if (downloadAll) downloadCSV(JSON.stringify(d, null, 2), 'mi-raw.json');
      return;
    }

    const { html, combinedCSV } = renderSections(sections);
    resultsEl.innerHTML = html;

    if (downloadAll && combinedCSV){
      downloadCSV(combinedCSV, 'mi-all.csv');
    }
  }

  if (previewBtn) previewBtn.addEventListener('click', () => doFetch(false));
  if (runBtn)     runBtn.addEventListener('click',   () => doFetch(true)); // "Download CSV" = Download ALL
})();
</script>
    <?php
}

/** AJAX: fetch report data (admin-only) */
add_action('wp_ajax_mihr_fetch', function () {
    if ( ! is_admin() || ! current_user_can('manage_options') ) {
        wp_send_json_error('No permission');
    }
    check_ajax_referer('mihr','nonce');

    if ( ! mihr_is_mi_active() ) {
        wp_send_json_error('MonsterInsights is not active.');
    }

    $start    = isset($_POST['start']) ? sanitize_text_field(wp_unslash($_POST['start'])) : '';
    $end      = isset($_POST['end'])   ? sanitize_text_field(wp_unslash($_POST['end']))   : '';
    $report   = isset($_POST['report']) ? sanitize_key($_POST['report']) : 'overview';
    $included = isset($_POST['included_metrics']) ? sanitize_text_field(wp_unslash($_POST['included_metrics'])) : '';

    foreach ([$start,$end] as $d) {
        if ( ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ) {
            wp_send_json_error('Invalid date format. Use YYYY-MM-DD.');
        }
    }

    if ( 'overview' !== $report ) {
        wp_send_json_error('Unsupported report for Lite.');
    }

    $args = array('start' => $start, 'end' => $end);
    if ( $included ) {
        $args['included_metrics'] = $included; // Lite may ignore premium metrics silently.
    }

    // Ensure API client is present even if not preloaded.
    if ( ! class_exists('MonsterInsights_API_Request') && defined('MONSTERINSIGHTS_PLUGIN_DIR') ) {
        $candidate = trailingslashit(MONSTERINSIGHTS_PLUGIN_DIR) . 'includes/api-request.php';
        if ( file_exists($candidate) ) {
            require_once $candidate;
        }
    }
    if ( ! class_exists('MonsterInsights_API_Request') ) {
        wp_send_json_error('MonsterInsights API client missing.');
    }

    $api = new MonsterInsights_API_Request( 'analytics/reports/' . $report . '/', $args, 'GET' );
    if ( method_exists($api, 'set_additional_data') ) {
        $api->set_additional_data( array( 'source' => 'mi-helper-reports' ) );
    }

    $resp = $api->request();

    if ( is_wp_error( $resp ) ) {
        wp_send_json_error( $resp->get_error_message() );
    }

    if ( is_array( $resp ) ) {
        // 1) Success shape A (older/Pro): success:true
        if ( isset( $resp['success'] ) ) {
            if ( ! $resp['success'] ) {
                $msg = isset($resp['data']['message']) ? $resp['data']['message'] : ( $resp['message'] ?? 'Unknown MI error' );
                wp_send_json_error( 'MI API error: ' . $msg );
            }
            wp_send_json_success( $resp['data'] ?? $resp );
        }

        // 2) Success shape B (Lite 9.x): error:false, status:200
        if ( array_key_exists( 'error', $resp ) ) {
            if ( $resp['error'] === false ) {
                wp_send_json_success( $resp['data'] ?? $resp );
            }
            $msg = isset($resp['data']['message']) ? $resp['data']['message'] : ( $resp['message'] ?? 'Unknown MI error' );
            wp_send_json_error( 'MI API error: ' . $msg );
        }

        // 3) Fallback: status 200 + data present
        if ( (isset($resp['status']) && (int)$resp['status'] === 200) && isset($resp['data']) ) {
            wp_send_json_success( $resp['data'] );
        }

        // 4) Last resort
        wp_send_json_error( 'MI API returned unexpected payload: ' . wp_json_encode( $resp ) );
    }

    wp_send_json_error( 'MI API returned unexpected payload: ' . print_r( $resp, true ) );
});
