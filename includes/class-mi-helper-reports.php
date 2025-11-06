<?php

if (!defined('ABSPATH')) {
    exit;
}

class MI_Helper_Reports
{
    private const INSIGHTS_PARENT_SLUG = 'monsterinsights_reports';

    private ?string $submenu_hook = null;

    public function init(): void
    {
        add_action('admin_menu', [$this, 'register_admin_menu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_mihr_fetch', [$this, 'handle_ajax']);
        add_filter('monsterinsights_api_request_body', [$this, 'clamp_future_dates']);
    }

    public function register_admin_menu(): void
    {
        if ($this->register_insights_menu_item()) {
            return;
        }

        add_management_page(
            __('MI Data Exports', 'mi-helper-reports'),
            __('MI Data Exports', 'mi-helper-reports'),
            'manage_options',
            'mi-helper-reports',
            [$this, 'render_tools_page']
        );
    }

    private function register_insights_menu_item(): bool
    {
        if (!defined('MONSTERINSIGHTS_VERSION')) {
            return false;
        }

        $this->submenu_hook = add_submenu_page(
            self::INSIGHTS_PARENT_SLUG,
            __('MI Data Exports', 'mi-helper-reports'),
            __('MI Data Exports', 'mi-helper-reports'),
            'manage_options',
            'mi-helper-reports',
            [$this, 'render_tools_page']
        );

        if (!$this->submenu_hook) {
            return false;
        }

        $this->move_submenu_to_top(self::INSIGHTS_PARENT_SLUG, 'mi-helper-reports');

        return true;
    }

    private function move_submenu_to_top(string $parent_slug, string $menu_slug): void
    {
        global $submenu;

        if (empty($submenu[$parent_slug])) {
            return;
        }

        foreach ($submenu[$parent_slug] as $index => $entry) {
            if (!isset($entry[2]) || $entry[2] !== $menu_slug) {
                continue;
            }

            $item = $entry;
            unset($submenu[$parent_slug][$index]);
            array_unshift($submenu[$parent_slug], $item);
            break;
        }
    }

    public function enqueue_assets(string $hook): void
    {
        if (!$this->should_enqueue_for_hook($hook)) {
            return;
        }

        $handle = 'mihr-admin';

        wp_register_script(
            $handle,
            MIHR_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            MIHR_VERSION,
            true
        );

        wp_localize_script(
            $handle,
            'mihrSettings',
            [
                'today'   => current_time('Y-m-d'),
                'strings' => [
                    'futureDateMessage' => __('Data for MonsterInsights is only available up to today. The end date has been reset to today.', 'mi-helper-reports'),
                    'noTopPages'        => __('No page data available for this period.', 'mi-helper-reports'),
                ],
            ]
        );

        wp_enqueue_script($handle);
    }

    private function should_enqueue_for_hook(string $hook): bool
    {
        if (false !== strpos($hook, 'mi-helper-reports')) {
            return true;
        }

        return false !== strpos($hook, self::INSIGHTS_PARENT_SLUG);
    }

    public function clamp_future_dates(array $body): array
    {
        if (empty($body['end'])) {
            return $body;
        }

        $today     = current_time('Y-m-d');
        $end       = $this->create_date_from_string($body['end']);
        $today_obj = $this->create_date_from_string($today);

        if (!$end || !$today_obj || $end <= $today_obj) {
            return $body;
        }

        $body['end'] = $today;

        if (!empty($body['start'])) {
            $start = $this->create_date_from_string($body['start']);
            if ($start && $start > $today_obj) {
                $body['start'] = $today;
            }
        }

        if (!empty($body['compare_end'])) {
            $compare_end = $this->create_date_from_string($body['compare_end']);
            if ($compare_end && $compare_end > $today_obj) {
                $body['compare_end'] = $today;
            }
        }

        if (!empty($body['compare_start'])) {
            $compare_start = $this->create_date_from_string($body['compare_start']);
            if ($compare_start && $compare_start > $today_obj) {
                $body['compare_start'] = $today;
            }
        }

        return $body;
    }

    private function create_date_from_string(string $date_string): ?\DateTimeImmutable
    {
        try {
            $date = new \DateTimeImmutable($date_string);
        } catch (\Exception $e) {
            return null;
        }

        return $date;
    }

    public function render_tools_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'mi-helper-reports'));
        }

        $nonce     = wp_create_nonce('mihr');
        $mi_active = $this->is_monsterinsights_active();

        $metric_defs = [
            'sessions'               => __('Sessions', 'mi-helper-reports'),
            'users'                  => __('Users', 'mi-helper-reports'),
            'newUsers'               => __('New users', 'mi-helper-reports'),
            'pageviews'              => __('Pageviews', 'mi-helper-reports'),
            'screenPageViews'        => __('Screen/Page views', 'mi-helper-reports'),
            'engagedSessions'        => __('Engaged sessions', 'mi-helper-reports'),
            'averageSessionDuration' => __('Avg. session duration', 'mi-helper-reports'),
            'bounceRate'             => __('Bounce rate', 'mi-helper-reports'),
        ];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('MI Data Exports', 'mi-helper-reports'); ?></h1>
            <?php if (!$mi_active) : ?>
                <div class="notice notice-warning"><p><?php esc_html_e('MonsterInsights Lite/Pro is not active. Please install/activate it first.', 'mi-helper-reports'); ?></p></div>
            <?php endif; ?>

            <form id="mihr-form" onsubmit="return false;" <?php echo $mi_active ? '' : 'style="opacity:.6;pointer-events:none"'; ?>>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="mihr-start"><?php esc_html_e('Start date', 'mi-helper-reports'); ?></label></th>
                        <td><input type="date" id="mihr-start" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mihr-end"><?php esc_html_e('End date', 'mi-helper-reports'); ?></label></th>
                        <td><input type="date" id="mihr-end" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mihr-report"><?php esc_html_e('Report', 'mi-helper-reports'); ?></label></th>
                        <td>
                            <select id="mihr-report">
                                <option value="overview">overview</option>
                                <option value="monthly">monthly</option>
                                <option value="top-pages">top 5 website pages</option>
                                <option value="traffic-overview">traffic overview</option>
                                <option value="channel-breakdown">channel breakdown</option>
                                <option value="social-snapshot">social snapshot</option>
                                <option value="referral-partners">referral partners</option>
                                <option value="blog-vs-traffic">blog vs traffic</option>
                                <option value="comparative-trends">comparative trends</option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Overview mirrors MonsterInsights Lite. Monthly aggregates KPI deltas. Top 5 Website Pages builds a per-month table of the most visited URLs.', 'mi-helper-reports'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Included metrics', 'mi-helper-reports'); ?></th>
                        <td>
                            <p style="margin:.5em 0">
                                <label><input type="checkbox" id="mihr-select-all"> <em><?php esc_html_e('Select all', 'mi-helper-reports'); ?></em></label>
                            </p>
                            <fieldset id="mihr-metrics-boxes">
                                <?php foreach ($metric_defs as $key => $label) : ?>
                                    <label style="display:inline-block;margin:0 16px 8px 0;">
                                        <input type="checkbox" class="mihr-metric" value="<?php echo esc_attr($key); ?>">
                                        <?php echo esc_html($label); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description"><?php esc_html_e('Check one or more. Lite may ignore premium-only metrics; that’s OK.', 'mi-helper-reports'); ?></p>
                        </td>
                    </tr>
                </table>
                <p>
                    <button class="button" id="mihr-preview"><?php esc_html_e('Preview', 'mi-helper-reports'); ?></button>
                    <button class="button button-primary" id="mihr-run"><?php esc_html_e('Download CSV', 'mi-helper-reports'); ?></button>
                </p>
            </form>

            <div id="mihr-status" style="margin-top:10px;"></div>
            <div id="mihr-column-controls" class="mihr-column-controls" style="margin-top:10px;"></div>
            <div id="mihr-results" style="margin-top:20px;"></div>
        </div>

        <script>
        (function(){
          const previewBtn = document.getElementById('mihr-preview');
          const runBtn     = document.getElementById('mihr-run');
          const statusEl   = document.getElementById('mihr-status');
          const resultsEl  = document.getElementById('mihr-results');
          const selectAll  = document.getElementById('mihr-select-all');
          const metricBoxes= Array.from(document.querySelectorAll('.mihr-metric'));
          const reportInput= document.getElementById('mihr-report');
          const columnControls = document.getElementById('mihr-column-controls');

          const settings = window.mihrSettings || {};
          const strings = settings.strings || {};
          const noTopPagesText = strings.noTopPages || 'No page data available for this period.';

          function esc(s){ return (''+s).replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;' }[m])); }
          function toNumber(x){ const n = typeof x === 'string' ? x.replace(/,/g,'').trim() : x; const f = parseFloat(n); return isFinite(f) ? f : null; }
          function toISODate(unix){ if (!unix) return ''; const d=new Date(unix*1000); const m=String(d.getMonth()+1).padStart(2,'0'); const day=String(d.getDate()).padStart(2,'0'); return d.getFullYear()+'-'+m+'-'+day; }
          function columnSlug(label){
            return label.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'col';
          }
          function ensureColumnSlug(label, slugMap){
            if (slugMap[label]) {
              return slugMap[label];
            }
            const used = new Set(Object.values(slugMap));
            let base = columnSlug(label);
            if (!base) {
              base = 'col';
            }
            let slug = base;
            let counter = 2;
            while (used.has(slug)) {
              slug = `${base}-${counter++}`;
            }
            slugMap[label] = slug;
            return slug;
          }

          if (selectAll) {
            selectAll.addEventListener('change', () => {
              metricBoxes.forEach(cb => cb.checked = selectAll.checked);
            });
          }

          function makeCSV(rows, cols){
            const head = cols.join(',');
            const body = rows.map(r => cols.map(c=>{
              const v = (r[c] ?? '').toString().replace(/"/g,'""');
              return /[",\n]/.test(v) ? '"'+v+'"' : v;
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
          function renderTable(title, rows, slugMap){
            if (!rows || !rows.length) {
              return { html: '', csv: '', columns: [] };
            }

            const cols = colsFromRows(rows);
            const columnMeta = cols.map((label, index) => {
              const slug = ensureColumnSlug(label, slugMap || {});
              return { label, slug: slug || `col-${index}` };
            });

            const header = columnMeta.map(col => `<th class="mihr-col-${col.slug}" data-column="${esc(col.label)}">${esc(col.label)}</th>`).join('');
            const body = rows.map(r => {
              const cells = columnMeta.map(col => `<td class="mihr-col-${col.slug}">${esc(r[col.label] ?? '')}</td>`).join('');
              return `<tr>${cells}</tr>`;
            }).join('');

            const html = [
              '<h3 style="margin-top:1.2em;">'+esc(title)+'</h3>',
              '<table class="widefat striped mihr-data-table"><thead><tr>',
              header,
              '</tr></thead><tbody>',
              body,
              '</tbody></table>'
            ].join('');

            const csv = makeCSV(rows, cols);
            return { html, csv, columns: columnMeta };
          }

          function buildSections(d){
            if (Array.isArray(d.mihr_sections) && d.mihr_sections.length) {
              return d.mihr_sections.map((sec, index) => {
                const rows = Array.isArray(sec.rows) ? sec.rows : [];
                return {
                  key: sec.key || `custom-${index}`,
                  title: sec.title || `Section ${index + 1}`,
                  rows,
                  summaryHtml: sec.summary ? `<p><strong>${esc(sec.summary)}</strong></p>` : '',
                  emptyText: sec.emptyText || '',
                  notes: Array.isArray(sec.notes) ? sec.notes : [],
                  downloadLabel: sec.downloadLabel || null,
                };
              });
            }

            if (d && d.mihr_mode === 'monthly' && Array.isArray(d.mihr_monthly_rows)) {
              return [{ key:'monthly', title:'Monthly Summary', rows: d.mihr_monthly_rows }];
            }
            if (d && d.mihr_mode === 'top-pages') {
              const allRows = Array.isArray(d.mihr_top_pages_rows) ? d.mihr_top_pages_rows : [];
              const summaryRows = Array.isArray(d.mihr_top_pages_summary_rows) ? d.mihr_top_pages_summary_rows : [];
              const summaryText = d.mihr_period_summary ? `<p><strong>${esc(d.mihr_period_summary)}</strong></p>` : '';
              const sections = [];

              if (summaryRows.length || summaryText) {
                sections.push({
                  key: 'top-pages-summary',
                  title: 'Top 5 Website Pages (overall)',
                  rows: summaryRows,
                  summaryHtml: summaryText,
                  emptyText: summaryRows.length ? '' : noTopPagesText,
                  notes: [ 'Totals reflect aggregated top-five pages across the selected period.' ]
                });
              }

              const emptyText = allRows.length ? '' : noTopPagesText;
              sections.push({
                key:'top-pages',
                title:'Top 5 Website Pages (monthly)',
                rows: allRows,
                summaryHtml: '',
                emptyText,
                notes: [ 'Monthly breakdown is limited to the top five pages reported by MonsterInsights for each month.' ]
              });
              return sections;
            }

            const sections = [];

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

            if (d.newvsreturn && (d.newvsreturn.new != null || d.newvsreturn.returning != null)){
              const rows = [];
              if (d.newvsreturn.new != null)       rows.push({ type:'new', value: d.newvsreturn.new });
              if (d.newvsreturn.returning != null) rows.push({ type:'returning', value: d.newvsreturn.returning });
              if (rows.length) sections.push({ key:'newvsreturn', title:'New vs Returning', rows });
            }

            if (d.devices){
              const rows = [];
              Object.keys(d.devices).forEach(k => rows.push({ device: k, percent: d.devices[k] }));
              if (rows.length) sections.push({ key:'devices', title:'Devices', rows });
            }

            if (Array.isArray(d.countries) && d.countries.length){
              const rows = d.countries.map(c => ({ iso: c.iso || '', sessions: c.sessions ?? '' }));
              sections.push({ key:'countries', title:'Countries', rows });
            }

            if (Array.isArray(d.referrals) && d.referrals.length){
              const rows = d.referrals.map(r => ({ url: r.url || '', sessions: r.sessions ?? '' }));
              sections.push({ key:'referrals', title:'Referrals', rows });
            }

            if (Array.isArray(d.toppages) && d.toppages.length){
              const rows = d.toppages.map(p => ({
                url: p.url || '', title: p.title || '', hostname: p.hostname || '', sessions: p.sessions ?? ''
              }));
              sections.push({ key:'toppages', title:'Top Pages', rows });
            }

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
            const csvMap = {};
            const columnsMap = {};
            const slugMap = {};
            sections.forEach(sec => {
              const tableData = renderTable(sec.title, sec.rows, slugMap);
              const { html, csv, columns } = tableData;
              if (Array.isArray(columns)) {
                columns.forEach(col => {
                  if (!columnsMap[col.slug]) {
                    columnsMap[col.slug] = col.label;
                  }
                });
              }
              const summary = sec.summaryHtml || '';
              const notes = Array.isArray(sec.notes) ? sec.notes.map(note => `<p>${esc(note)}</p>`).join('') : '';
              const header = summary + notes;

              if (html){
                const btnId = `mihr-dl-${sec.key}`;
                const downloadLabel = sec.downloadLabel ? esc(sec.downloadLabel) : `Download ${esc(sec.title)} CSV`;
                parts.push(header + html + `<p><button type="button" class="button" id="${btnId}">${downloadLabel}</button></p>`);
                csvMap[sec.key] = csv;
                setTimeout(() => {
                  const el = document.getElementById(btnId);
                  if (el) el.addEventListener('click', ()=> downloadCSV(csv, `mi-${sec.key}.csv`));
                }, 0);
              } else if (summary || notes || sec.emptyText) {
                const message = sec.emptyText ? `<p>${esc(sec.emptyText)}</p>` : '';
                parts.push(header + message);
              }
            });

            const combined = sections
              .filter(sec => csvMap[sec.key])
              .map(sec => {
                const titleLine = `# ${sec.title}`;
                return titleLine + '\n' + csvMap[sec.key];
              })
              .join('\n\n');

          return { html: parts.join(''), combinedCSV: combined, columns: columnsMap };
          }

          function setupColumnControls(columns){
            if (!columnControls) {
              return;
            }

            columnControls.innerHTML = '';
            const entries = Object.entries(columns || {});
            if (!entries.length) {
              columnControls.style.display = 'none';
              return;
            }

            columnControls.style.display = '';

            const wrapper = document.createElement('div');
            wrapper.className = 'mihr-column-toggle-list';

            const heading = document.createElement('strong');
            heading.textContent = '<?php echo esc_js(__('Columns:', 'mi-helper-reports')); ?>';
            heading.style.marginRight = '8px';
            wrapper.appendChild(heading);

            entries.forEach(([slug, label]) => {
              const controlLabel = document.createElement('label');
              controlLabel.style.marginRight = '16px';

              const checkbox = document.createElement('input');
              checkbox.type = 'checkbox';
              checkbox.checked = true;
              checkbox.dataset.columnSlug = slug;
              controlLabel.appendChild(checkbox);
              controlLabel.appendChild(document.createTextNode(' ' + label));

              wrapper.appendChild(controlLabel);
            });

            columnControls.appendChild(wrapper);

            function applyVisibility() {
              entries.forEach(([slug]) => {
                const isChecked = columnControls.querySelector(`input[data-column-slug="${slug}"]`)?.checked !== false;
                document.querySelectorAll(`.mihr-col-${slug}`).forEach(element => {
                  element.style.display = isChecked ? '' : 'none';
                });
              });
            }

            columnControls.querySelectorAll('input[data-column-slug]').forEach(input => {
              input.addEventListener('change', applyVisibility);
            });

            applyVisibility();
          }

          async function doFetch(downloadAll){
            statusEl.textContent = 'Fetching…';
            resultsEl.innerHTML = '';
            setupColumnControls({});

            const selectedMetrics = metricBoxes.filter(cb => cb.checked).map(cb => cb.value);
            const payload = new URLSearchParams();
            payload.set('action','mihr_fetch');
            payload.set('nonce','<?php echo esc_js($nonce); ?>');
            payload.set('start', document.getElementById('mihr-start').value || '');
            payload.set('end',   document.getElementById('mihr-end').value   || '');
            payload.set('report', reportInput ? (reportInput.value || 'overview') : 'overview');
            if (selectedMetrics.length) payload.set('included_metrics', selectedMetrics.join(','));

            const res = await fetch(ajaxurl, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: payload.toString() });
            let data; try { data = await res.json(); } catch(e){ data = { success:false, data:'Invalid JSON response' }; }

            if (!data || !data.success) {
              statusEl.innerHTML = '<span style="color:#b00;">' + esc((data && data.data) || 'Request failed') + '</span>';
              setupColumnControls({});
              return;
            }
            statusEl.textContent = 'OK';

            const d = data.data || {};
            const sections = buildSections(d);

            if (!sections.length){
              resultsEl.innerHTML = '<pre style="max-height:480px;overflow:auto;">' + esc(JSON.stringify(d, null, 2)) + '</pre>';
              if (downloadAll) downloadCSV(JSON.stringify(d, null, 2), 'mi-raw.json');
              setupColumnControls({});
              return;
            }

            const { html, combinedCSV, columns } = renderSections(sections);
            resultsEl.innerHTML = html;
            setupColumnControls(columns);

            if (downloadAll && combinedCSV){
              downloadCSV(combinedCSV, 'mi-all.csv');
            }
          }

          if (previewBtn) previewBtn.addEventListener('click', () => doFetch(false));
          if (runBtn)     runBtn.addEventListener('click',   () => doFetch(true));
        })();
        </script>
        <?php
    }
    private function is_monsterinsights_active(): bool
    {
        $has_core = class_exists('MonsterInsights_Lite')
            || class_exists('MonsterInsights')
            || defined('MONSTERINSIGHTS_VERSION')
            || defined('MONSTERINSIGHTS_LITE_VERSION');

        if ($has_core && !class_exists('MonsterInsights_API_Request')) {
            if (defined('MONSTERINSIGHTS_PLUGIN_DIR')) {
                $candidate = trailingslashit(MONSTERINSIGHTS_PLUGIN_DIR) . 'includes/api-request.php';
                if (file_exists($candidate)) {
                    require_once $candidate;
                }
            }
        }

        return $has_core && class_exists('MonsterInsights_API_Request');
    }

    public function handle_ajax(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to access this endpoint.', 'mi-helper-reports'));
        }

        check_ajax_referer('mihr', 'nonce');

        if (!$this->is_monsterinsights_active()) {
            wp_send_json_error(__('MonsterInsights is not active.', 'mi-helper-reports'));
        }

        $start    = isset($_POST['start']) ? sanitize_text_field(wp_unslash($_POST['start'])) : '';
        $end      = isset($_POST['end']) ? sanitize_text_field(wp_unslash($_POST['end'])) : '';
        $report   = isset($_POST['report']) ? sanitize_key(wp_unslash($_POST['report'])) : 'overview';
        $included = isset($_POST['included_metrics']) ? sanitize_text_field(wp_unslash($_POST['included_metrics'])) : '';

        foreach ([$start, $end] as $date) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                wp_send_json_error(__('Invalid date format. Use YYYY-MM-DD.', 'mi-helper-reports'));
            }
        }

        if ('monthly' === $report) {
            $payload = $this->build_monthly_payload($start, $end, $included);
            if (is_wp_error($payload)) {
                wp_send_json_error($payload->get_error_message());
            }
            wp_send_json_success($payload);
        }

        if ('top-pages' === $report) {
            $payload = $this->build_top_pages_payload($start, $end, $included);
            if (is_wp_error($payload)) {
                wp_send_json_error($payload->get_error_message());
            }
            wp_send_json_success($payload);
        }

        if ('traffic-overview' === $report) {
            $payload = $this->build_traffic_overview_payload($start, $end, $included);
            if (is_wp_error($payload)) {
                wp_send_json_error($payload->get_error_message());
            }
            wp_send_json_success($payload);
        }

        if ('channel-breakdown' === $report) {
            $payload = $this->build_channel_breakdown_payload($start, $end, $included);
            if (is_wp_error($payload)) {
                wp_send_json_error($payload->get_error_message());
            }
            wp_send_json_success($payload);
        }

        if ('social-snapshot' === $report) {
            $payload = $this->build_social_snapshot_payload($start, $end, $included);
            if (is_wp_error($payload)) {
                wp_send_json_error($payload->get_error_message());
            }
            wp_send_json_success($payload);
        }

        if ('referral-partners' === $report) {
            $payload = $this->build_referral_partners_payload($start, $end, $included);
            if (is_wp_error($payload)) {
                wp_send_json_error($payload->get_error_message());
            }
            wp_send_json_success($payload);
        }

        if ('blog-vs-traffic' === $report) {
            $payload = $this->build_blog_vs_traffic_payload($start, $end, $included);
            if (is_wp_error($payload)) {
                wp_send_json_error($payload->get_error_message());
            }
            wp_send_json_success($payload);
        }

        if ('comparative-trends' === $report) {
            $payload = $this->build_comparative_trends_payload($start, $end, $included);
            if (is_wp_error($payload)) {
                wp_send_json_error($payload->get_error_message());
            }
            wp_send_json_success($payload);
        }

        $args = ['start' => $start, 'end' => $end];
        if ($included) {
            $args['included_metrics'] = $included;
        }

        $response = $this->request_monsterinsights_report($report, $args);
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        wp_send_json_success($response);
    }

    private function build_monthly_payload(string $start, string $end, string $included)
    {
        $current_args = ['start' => $start, 'end' => $end];
        if ($included) {
            $current_args['included_metrics'] = $included;
        }

        $current = $this->request_monsterinsights_report('overview', $current_args);
        if (is_wp_error($current)) {
            return $current;
        }

        $range = $this->compute_previous_range($start, $end);
        $previous = null;

        if ($range) {
            $previous_args = ['start' => $range['start'], 'end' => $range['end']];
            if ($included) {
                $previous_args['included_metrics'] = $included;
            }
            $previous = $this->request_monsterinsights_report('overview', $previous_args);
            if (is_wp_error($previous)) {
                $previous = null;
            }
        }

        $row = $this->transform_monthly_row($current, $previous, $start, $end);

        return [
            'mihr_mode'         => 'monthly',
            'mihr_monthly_rows' => [$row],
        ];
    }

    private function build_top_pages_payload(string $start, string $end, string $included)
    {
        $periods = $this->generate_monthly_periods($start, $end);
        if (empty($periods)) {
            return new \WP_Error('mihr-periods', __('Unable to determine time slices for this range.', 'mi-helper-reports'));
        }

        $rows         = [];
        $agg         = [];
        $steps        = count($periods);

        foreach ($periods as $slice) {
            $args = [
                'start' => $slice['start'],
                'end'   => $slice['end'],
            ];
            if ($included) {
                $args['included_metrics'] = $included;
            }

            $data = $this->request_monsterinsights_report('overview', $args);
            if (is_wp_error($data)) {
                return $data;
            }

            if (!empty($data['toppages']) && is_array($data['toppages'])) {
                $ranked = array_slice($data['toppages'], 0, 5);
                foreach ($ranked as $index => $page) {
                    $sessions_val = $this->parse_numeric($page['sessions'] ?? '') ?? 0.0;
                    $rows[] = [
                        'period' => $slice['label'],
                        'year'   => $slice['year'],
                        'month'  => $slice['month'],
                        'rank'   => (string) ($index + 1),
                        'url'    => $page['url'] ?? '',
                        'title'  => $page['title'] ?? '',
                        'hostname' => $page['hostname'] ?? '',
                        'sessions' => isset($page['sessions']) ? (string) $page['sessions'] : '',
                    ];

                    $agg_key = $page['url'] ?? '';
                    if ($agg_key === '') {
                        continue;
                    }

                    if (!isset($agg[$agg_key])) {
                        $agg[$agg_key] = [
                            'url'      => $page['url'] ?? '',
                            'title'    => $page['title'] ?? '',
                            'hostname' => $page['hostname'] ?? '',
                            'sessions' => 0.0,
                        ];
                    }

                    $agg[$agg_key]['sessions'] += $sessions_val;
                }
            }
        }

        $summary = $this->format_period_summary($start, $end) . ' — ' . sprintf(
            /* translators: %d processed slices */
            __('%d monthly slice(s) processed.', 'mi-helper-reports'),
            $steps
        );

        $summary_rows = [];
        if (!empty($agg)) {
            uasort($agg, static function ($a, $b) {
                return $b['sessions'] <=> $a['sessions'];
            });

            $rank = 1;
            foreach (array_slice($agg, 0, 5) as $item) {
                $summary_rows[] = [
                    'period'   => __('Entire Period', 'mi-helper-reports'),
                    'year'     => '',
                    'month'    => '',
                    'rank'     => (string) $rank,
                    'url'      => $item['url'],
                    'title'    => $item['title'],
                    'hostname' => $item['hostname'],
                    'sessions' => number_format((float) $item['sessions']),
                ];
                $rank++;
            }
        }

        return [
            'mihr_mode'            => 'top-pages',
            'mihr_period_summary'  => $summary,
            'mihr_top_pages_summary_rows' => $summary_rows,
            'mihr_top_pages_rows'  => $rows,
        ];
    }

    private function build_traffic_overview_payload(string $start, string $end, string $included)
    {
        $current_args = ['start' => $start, 'end' => $end];
        if ($included) {
            $current_args['included_metrics'] = $included;
        }

        $current = $this->request_monsterinsights_report('overview', $current_args);
        if (is_wp_error($current)) {
            return $current;
        }

        $previous = null;
        $range = $this->compute_previous_range($start, $end);
        if ($range) {
            $previous_args = ['start' => $range['start'], 'end' => $range['end']];
            if ($included) {
                $previous_args['included_metrics'] = $included;
            }
            $previous = $this->request_monsterinsights_report('overview', $previous_args);
            if (is_wp_error($previous)) {
                $previous = null;
            }
        }

        $sections = [];
        $period_summary = $this->format_period_summary($start, $end);

        [$current_label, $previous_label] = $this->build_period_labels($start, $end, $range);

        $sections[] = [
            'key'     => 'traffic-overview-headline',
            'title'   => sprintf(
                __('Overall Visits (%s vs %s)', 'mi-helper-reports'),
                $this->format_range_label($start, $end),
                $range ? $this->format_range_label($range['start'], $range['end']) : __('Previous period', 'mi-helper-reports')
            ),
            'summary' => $period_summary,
            'rows'    => $this->build_metric_comparison_rows($current, $previous, [
                'sessions',
                'totalusers',
                'pageviews',
            ], $current_label, $previous_label),
        ];

        $sections[] = [
            'key'   => 'traffic-overview-engagement',
            'title' => sprintf(
                __('Engagement Pulse (%s vs %s)', 'mi-helper-reports'),
                $this->format_range_label($start, $end),
                $range ? $this->format_range_label($range['start'], $range['end']) : __('Previous period', 'mi-helper-reports')
            ),
            'rows'  => $this->build_metric_comparison_rows($current, $previous, [
                'duration',
                'bounce_rate',
                'engagedSessions',
            ], $current_label, $previous_label),
            'notes' => [
                __('Average session duration is reported in MonsterInsights format. Percentage change compares the selected period with the prior period.', 'mi-helper-reports'),
            ],
        ];

        $sections[] = [
            'key'   => 'traffic-overview-devices',
            'title' => __('Device Mix', 'mi-helper-reports'),
            'rows'  => $this->build_device_rows($current),
            'emptyText' => __('Device share information is unavailable for the selected period.', 'mi-helper-reports'),
            'notes' => [
                __('Percentages are supplied by MonsterInsights and may not total 100% when rounding is applied.', 'mi-helper-reports'),
            ],
        ];

        return [
            'mihr_mode'     => 'custom',
            'mihr_sections' => $sections,
        ];
    }

    private function build_channel_breakdown_payload(string $start, string $end, string $included)
    {
        $current_args = ['start' => $start, 'end' => $end];
        if ($included) {
            $current_args['included_metrics'] = $included;
        }

        $current = $this->request_monsterinsights_report('overview', $current_args);
        if (is_wp_error($current)) {
            return $current;
        }

        $total_sessions = $this->metric_raw_value($current, 'sessions') ?? 0.0;
        $channels = $this->estimate_channel_shares($current, $total_sessions);

        $rows = [];
        foreach ($channels as $item) {
            $share = $total_sessions > 0 ? $this->format_percent_value(($item['sessions'] / $total_sessions) * 100) : '';
            $rows[] = [
                'channel'  => $item['label'],
                'sessions' => $this->format_number($item['sessions']),
                'share'    => $share,
            ];
        }

        return [
            'mihr_mode'     => 'custom',
            'mihr_sections' => [
                [
                    'key'       => 'channel-breakdown',
                    'title'     => __('Channel Breakdown', 'mi-helper-reports'),
                    'summary'   => $this->format_period_summary($start, $end),
                    'rows'      => $rows,
                    'emptyText' => __('Channel shares could not be calculated from the available data.', 'mi-helper-reports'),
                    'notes'     => [
                        __('Social and referral shares are estimated from the top referral sources. Direct/Other is computed as remaining traffic when MonsterInsights Lite does not expose full channel data.', 'mi-helper-reports'),
                    ],
                ],
            ],
        ];
    }

    private function build_social_snapshot_payload(string $start, string $end, string $included)
    {
        $current_args = ['start' => $start, 'end' => $end];
        if ($included) {
            $current_args['included_metrics'] = $included;
        }

        $current = $this->request_monsterinsights_report('overview', $current_args);
        if (is_wp_error($current)) {
            return $current;
        }

        $total_sessions = $this->metric_raw_value($current, 'sessions') ?? 0.0;
        $sources = $this->collect_social_sources($current);

        $rows = [];
        $social_total = array_sum($sources);

        foreach ($sources as $network => $sessions) {
            $rows[] = [
                'network'         => $network,
                'sessions'        => $this->format_number($sessions),
                'share_of_social' => $social_total > 0 ? $this->format_percent_value(($sessions / $social_total) * 100) : '',
                'share_of_total'  => $total_sessions > 0 ? $this->format_percent_value(($sessions / $total_sessions) * 100) : '',
            ];
        }

        return [
            'mihr_mode'     => 'custom',
            'mihr_sections' => [
                [
                    'key'       => 'social-snapshot',
                    'title'     => __('Social Snapshot', 'mi-helper-reports'),
                    'summary'   => $this->format_period_summary($start, $end),
                    'rows'      => $rows,
                    'emptyText' => __('No social traffic was detected for the selected period.', 'mi-helper-reports'),
                    'notes'     => [
                        __('Social traffic is approximated from referral hosts for popular social networks; traffic from other networks is included in Direct / Other.', 'mi-helper-reports'),
                    ],
                ],
            ],
        ];
    }

    private function build_referral_partners_payload(string $start, string $end, string $included)
    {
        $current_args = ['start' => $start, 'end' => $end];
        if ($included) {
            $current_args['included_metrics'] = $included;
        }

        $current = $this->request_monsterinsights_report('overview', $current_args);
        if (is_wp_error($current)) {
            return $current;
        }

        $rows = [];
        if (!empty($current['referrals']) && is_array($current['referrals'])) {
            foreach ($current['referrals'] as $entry) {
                $host = $this->parse_host($entry['url'] ?? '') ?: strtolower($entry['hostname'] ?? '');
                if ($host === '' || $this->is_social_host($host)) {
                    continue;
                }

                $sessions = $this->parse_numeric($entry['sessions'] ?? '') ?? 0.0;
                if ($sessions <= 0) {
                    continue;
                }

                $rows[] = [
                    'referrer' => $host,
                    'url'      => $entry['url'] ?? ($entry['hostname'] ?? ''),
                    'sessions' => $this->format_number($sessions),
                ];
            }
        }

        return [
            'mihr_mode'     => 'custom',
            'mihr_sections' => [
                [
                    'key'       => 'referral-partners',
                    'title'     => __('Referral Partners', 'mi-helper-reports'),
                    'summary'   => $this->format_period_summary($start, $end),
                    'rows'      => $rows,
                    'emptyText' => __('No referral partners were returned for the selected period.', 'mi-helper-reports'),
                    'notes'     => [
                        __('Referral data is based on the top sources provided by MonsterInsights; long-tail referrers may be omitted.', 'mi-helper-reports'),
                    ],
                ],
            ],
        ];
    }

    private function build_blog_vs_traffic_payload(string $start, string $end, string $included)
    {
        $current_args = ['start' => $start, 'end' => $end];
        if ($included) {
            $current_args['included_metrics'] = $included;
        }

        $current = $this->request_monsterinsights_report('overview', $current_args);
        if (is_wp_error($current)) {
            return $current;
        }

        $blog_posts = $this->count_blog_posts($start, $end);
        $sessions   = $this->metric_raw_value($current, 'sessions') ?? 0.0;
        $pageviews  = $this->metric_raw_value($current, 'pageviews');
        if ($pageviews === null) {
            $pageviews = $this->metric_raw_value($current, 'screenPageViews') ?? 0.0;
        }

        $rows = [
            [
                'metric' => __('Published posts', 'mi-helper-reports'),
                'value'  => $this->format_number($blog_posts),
            ],
            [
                'metric' => __('Sessions', 'mi-helper-reports'),
                'value'  => $this->format_number($sessions),
            ],
            [
                'metric' => __('Pageviews', 'mi-helper-reports'),
                'value'  => $this->format_number($pageviews),
            ],
        ];

        if ($blog_posts > 0) {
            $rows[] = [
                'metric' => __('Sessions per post', 'mi-helper-reports'),
                'value'  => $this->format_number($sessions / $blog_posts, 1),
            ];
            $rows[] = [
                'metric' => __('Pageviews per post', 'mi-helper-reports'),
                'value'  => $this->format_number($pageviews / $blog_posts, 1),
            ];
        }

        return [
            'mihr_mode'     => 'custom',
            'mihr_sections' => [
                [
                    'key'     => 'blog-vs-traffic',
                    'title'   => __('Blog Output vs. Traffic', 'mi-helper-reports'),
                    'summary' => $this->format_period_summary($start, $end),
                    'rows'    => $rows,
                    'notes'   => [
                        __('Sessions and pageviews reflect MonsterInsights overview data for the selected period.', 'mi-helper-reports'),
                        __('Published post count is derived from WordPress posts with the “post” type and status “publish”.', 'mi-helper-reports'),
                    ],
                ],
            ],
        ];
    }

    private function build_comparative_trends_payload(string $start, string $end, string $included)
    {
        $current_args = ['start' => $start, 'end' => $end];
        if ($included) {
            $current_args['included_metrics'] = $included;
        }

        $current = $this->request_monsterinsights_report('overview', $current_args);
        if (is_wp_error($current)) {
            return $current;
        }

        $previous = null;
        $range = $this->compute_previous_range($start, $end);
        if ($range) {
            $previous_args = ['start' => $range['start'], 'end' => $range['end']];
            if ($included) {
                $previous_args['included_metrics'] = $included;
            }
            $previous = $this->request_monsterinsights_report('overview', $previous_args);
            if (is_wp_error($previous)) {
                $previous = null;
            }
        }

        $sections = [];

        [$current_label, $previous_label] = $this->build_period_labels($start, $end, $range);

        $sections[] = [
            'key'   => 'comparative-headline',
            'title' => sprintf(
                __('Period vs. Previous (%s vs %s)', 'mi-helper-reports'),
                $this->format_range_label($start, $end),
                $range ? $this->format_range_label($range['start'], $range['end']) : __('Previous period', 'mi-helper-reports')
            ),
            'summary' => $this->format_period_summary($start, $end),
            'rows'  => $this->build_metric_comparison_rows($current, $previous, [
                'sessions',
                'totalusers',
                'pageviews',
                'engagedSessions',
            ], $current_label, $previous_label),
            'notes' => [
                __('If prior-period data cannot be retrieved, change values will remain blank.', 'mi-helper-reports'),
            ],
        ];

        $sections[] = [
            'key'   => 'comparative-engagement',
            'title' => sprintf(
                __('Engagement & Quality (%s vs %s)', 'mi-helper-reports'),
                $this->format_range_label($start, $end),
                $range ? $this->format_range_label($range['start'], $range['end']) : __('Previous period', 'mi-helper-reports')
            ),
            'rows'  => $this->build_metric_comparison_rows($current, $previous, [
                'duration',
                'bounce_rate',
                'new_users',
            ], $current_label, $previous_label),
        ];

        $sections[] = [
            'key'   => 'comparative-new-vs-returning',
            'title' => sprintf(
                __('New vs. Returning Visitors (%s vs %s)', 'mi-helper-reports'),
                $this->format_range_label($start, $end),
                $range ? $this->format_range_label($range['start'], $range['end']) : __('Previous period', 'mi-helper-reports')
            ),
            'rows'  => $this->build_new_vs_returning_rows($current, $previous, $current_label, $previous_label),
            'notes' => [
                __('Percentages represent the share of total sessions reported by MonsterInsights for each period.', 'mi-helper-reports'),
            ],
        ];

        return [
            'mihr_mode'     => 'custom',
            'mihr_sections' => $sections,
        ];
    }

    private function request_monsterinsights_report(string $report, array $args)
    {
        $api = $this->make_api_client($report, $args);
        if (is_wp_error($api)) {
            return $api;
        }

        $response = $api->request();
        if (is_wp_error($response)) {
            return $response;
        }

        if (!is_array($response)) {
            return new \WP_Error('mihr-api', __('MonsterInsights API returned unexpected response.', 'mi-helper-reports'));
        }

        if (isset($response['success'])) {
            if (!$response['success']) {
                $message = isset($response['data']['message']) ? $response['data']['message'] : ($response['message'] ?? __('Unknown MonsterInsights error.', 'mi-helper-reports'));
                return new \WP_Error('mihr-api', sprintf(__('MI API error: %s', 'mi-helper-reports'), $message));
            }
            return $response['data'] ?? $response;
        }

        if (array_key_exists('error', $response)) {
            if (false === $response['error']) {
                return $response['data'] ?? $response;
            }
            $message = isset($response['data']['message']) ? $response['data']['message'] : ($response['message'] ?? __('Unknown MonsterInsights error.', 'mi-helper-reports'));
            return new \WP_Error('mihr-api', sprintf(__('MI API error: %s', 'mi-helper-reports'), $message));
        }

        if ((isset($response['status']) && (int) $response['status'] === 200) && isset($response['data'])) {
            return $response['data'];
        }

        return new \WP_Error('mihr-api', __('MonsterInsights API returned unexpected payload.', 'mi-helper-reports'));
    }

    private function make_api_client(string $report, array $args)
    {
        if (!class_exists('MonsterInsights_API_Request')) {
            if (defined('MONSTERINSIGHTS_PLUGIN_DIR')) {
                $candidate = trailingslashit(MONSTERINSIGHTS_PLUGIN_DIR) . 'includes/api-request.php';
                if (file_exists($candidate)) {
                    require_once $candidate;
                }
            }
        }

        if (!class_exists('MonsterInsights_API_Request')) {
            return new \WP_Error('mihr-api', __('MonsterInsights API client missing.', 'mi-helper-reports'));
        }

        return new \MonsterInsights_API_Request('analytics/reports/' . $report . '/', $args, 'GET');
    }

    private function compute_previous_range(string $start, string $end): ?array
    {
        $start_obj = $this->create_date_from_string($start);
        $end_obj   = $this->create_date_from_string($end);

        if (!$start_obj || !$end_obj) {
            return null;
        }

        $days = (int) $end_obj->diff($start_obj)->format('%a');
        $days = max($days, 0) + 1;

        $prev_end = $start_obj->modify('-1 day');
        if (!$prev_end) {
            return null;
        }

        $prev_start = $prev_end->modify('-' . ($days - 1) . ' days');
        if (!$prev_start) {
            return null;
        }

        return [
            'start' => $prev_start->format('Y-m-d'),
            'end'   => $prev_end->format('Y-m-d'),
        ];
    }

    private function generate_monthly_periods(string $start, string $end): array
    {
        $start_obj = $this->create_date_from_string($start);
        $end_obj   = $this->create_date_from_string($end);

        if (!$start_obj || !$end_obj || $start_obj > $end_obj) {
            return [];
        }

        $periods = [];
        $cursor  = $start_obj->modify('first day of this month');
        $end_ts  = $end_obj->getTimestamp();
        $start_ts = $start_obj->getTimestamp();

        while ($cursor->getTimestamp() <= $end_ts) {
            $slice_start = $cursor;
            if ($slice_start->getTimestamp() < $start_ts) {
                $slice_start = $start_obj;
            }

            $slice_end = $cursor->modify('last day of this month');
            if ($slice_end->getTimestamp() > $end_ts) {
                $slice_end = $end_obj;
            }

            $periods[] = [
                'start' => $slice_start->format('Y-m-d'),
                'end'   => $slice_end->format('Y-m-d'),
                'year'  => $slice_start->format('Y'),
                'month' => $slice_start->format('m'),
                'label' => $slice_start->format('F Y'),
            ];

            $cursor = $cursor->modify('first day of next month');
        }

        return $periods;
    }

    private function format_period_summary(string $start, string $end): string
    {
        $start_obj = $this->create_date_from_string($start);
        $end_obj   = $this->create_date_from_string($end);

        if (!$start_obj || !$end_obj) {
            return '';
        }

        $days = (int) $end_obj->diff($start_obj)->format('%a') + 1;
        $interval = $start_obj->diff($end_obj);
        $years = (int) $interval->format('%y');
        $months = (int) $interval->format('%m');

        return sprintf(
            /* translators: 1: total days 2: years 3: months */
            __('%1$d day(s) in period (%2$d year(s), %3$d month(s))', 'mi-helper-reports'),
            $days,
            $years,
            $months
        );
    }

    private function format_range_label(string $start, string $end): string
    {
        $start_obj = $this->create_date_from_string($start);
        $end_obj   = $this->create_date_from_string($end);

        if (!$start_obj || !$end_obj) {
            return __('Unknown range', 'mi-helper-reports');
        }

        return sprintf('%s – %s', $start_obj->format('Y-m-d'), $end_obj->format('Y-m-d'));
    }

    private function build_period_labels(string $start, string $end, ?array $previous_range): array
    {
        $current_label = sprintf(__('Current (%s)', 'mi-helper-reports'), $this->format_range_label($start, $end));
        $previous_label = $previous_range
            ? sprintf(__('Previous (%s)', 'mi-helper-reports'), $this->format_range_label($previous_range['start'], $previous_range['end']))
            : __('Previous', 'mi-helper-reports');

        return [$current_label, $previous_label];
    }

    private function build_metric_comparison_rows(?array $current, ?array $previous, array $keys, ?string $current_label = null, ?string $previous_label = null): array
    {
        $labels = [
            'sessions'        => __('Sessions', 'mi-helper-reports'),
            'totalusers'      => __('Users', 'mi-helper-reports'),
            'pageviews'       => __('Pageviews', 'mi-helper-reports'),
            'engagedSessions' => __('Engaged sessions', 'mi-helper-reports'),
            'duration'        => __('Avg. session duration', 'mi-helper-reports'),
            'bounce_rate'     => __('Bounce rate', 'mi-helper-reports'),
            'new_users'       => __('New users', 'mi-helper-reports'),
        ];

        $current_label = $current_label ?? __('Current', 'mi-helper-reports');
        $previous_label = $previous_label ?? __('Previous', 'mi-helper-reports');
        $change_label = __('Change', 'mi-helper-reports');

        $rows = [];
        foreach ($keys as $key) {
            $label = $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
            [$current_display, $current_raw] = $this->extract_metric_pair($current, $key);
            [$previous_display, $previous_raw] = $this->extract_metric_pair($previous, $key);

            $change = $this->format_percent_change($current_raw, $previous_raw);

            $rows[] = [
                'metric'   => $label,
                $current_label  => $current_display,
                $previous_label => $previous_display,
                $change_label   => $change,
            ];
        }

        return $rows;
    }

    private function build_device_rows(?array $current): array
    {
        if (empty($current['devices']) || !is_array($current['devices'])) {
            return [];
        }

        $rows = [];
        foreach ($current['devices'] as $device => $share) {
            $rows[] = [
                'device' => ucfirst(strtolower($device)),
                'share'  => $share,
            ];
        }

        return $rows;
    }

    private function estimate_channel_shares(array $current, float $total_sessions): array
    {
        $social_sources = $this->collect_social_sources($current);
        $social_total   = array_sum($social_sources);

        $referral_total = 0.0;
        if (!empty($current['referrals']) && is_array($current['referrals'])) {
            foreach ($current['referrals'] as $entry) {
                $host = $this->parse_host($entry['url'] ?? '') ?: strtolower($entry['hostname'] ?? '');
                if ($host === '' || $this->is_social_host($host)) {
                    continue;
                }
                $referral_total += $this->parse_numeric($entry['sessions'] ?? '') ?? 0.0;
            }
        }

        $direct = $total_sessions - ($social_total + $referral_total);
        if ($direct < 0) {
            $direct = 0.0;
        }

        return [
            [
                'label'    => __('Direct / Other', 'mi-helper-reports'),
                'sessions' => $direct,
            ],
            [
                'label'    => __('Social', 'mi-helper-reports'),
                'sessions' => $social_total,
            ],
            [
                'label'    => __('Referral', 'mi-helper-reports'),
                'sessions' => $referral_total,
            ],
        ];
    }

    private function collect_social_sources(array $current): array
    {
        $totals = [];

        if (!empty($current['referrals']) && is_array($current['referrals'])) {
            foreach ($current['referrals'] as $entry) {
                $host = $this->parse_host($entry['url'] ?? '') ?: strtolower($entry['hostname'] ?? '');
                if ($host === '') {
                    continue;
                }

                $label = $this->get_social_label_for_host($host);
                if (!$label) {
                    continue;
                }

                $sessions = $this->parse_numeric($entry['sessions'] ?? '') ?? 0.0;
                if ($sessions <= 0) {
                    continue;
                }

                if (!isset($totals[$label])) {
                    $totals[$label] = 0.0;
                }

                $totals[$label] += $sessions;
            }
        }

        return $totals;
    }

    private function parse_host(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $parts = wp_parse_url($url);
        if (is_array($parts) && isset($parts['host'])) {
            return strtolower($parts['host']);
        }

        if (strpos($url, '/') === false && strpos($url, ' ') === false) {
            return strtolower($url);
        }

        return '';
    }

    private function is_social_host(string $host): bool
    {
        return (bool) $this->get_social_label_for_host($host);
    }

    private function get_social_label_for_host(string $host): ?string
    {
        $map = [
            'facebook.com'      => 'Facebook',
            'm.facebook.com'    => 'Facebook',
            'lm.facebook.com'   => 'Facebook',
            'l.facebook.com'    => 'Facebook',
            'web.facebook.com'  => 'Facebook',
            'linkedin.com'      => 'LinkedIn',
            'lnkd.in'           => 'LinkedIn',
            'instagram.com'     => 'Instagram',
            'l.instagram.com'   => 'Instagram',
            'twitter.com'       => 'Twitter',
            'x.com'             => 'X',
            't.co'              => 'X',
            'youtube.com'       => 'YouTube',
            'youtu.be'          => 'YouTube',
            'pinterest.com'     => 'Pinterest',
            'reddit.com'        => 'Reddit',
            'threads.net'       => 'Threads',
            'mastodon.social'   => 'Mastodon',
        ];

        foreach ($map as $needle => $label) {
            if ($host === $needle) {
                return $label;
            }

            $suffix = '.' . $needle;
            if (strlen($host) > strlen($suffix) && substr($host, -strlen($suffix)) === $suffix) {
                return $label;
            }
        }

        $patterns = [
            'facebook.'  => 'Facebook',
            'instagram.' => 'Instagram',
            'linkedin.'  => 'LinkedIn',
            'twitter.'   => 'Twitter',
            'tiktok.'    => 'TikTok',
            'snapchat.'  => 'Snapchat',
        ];

        foreach ($patterns as $needle => $label) {
            if (strpos($host, $needle) !== false) {
                return $label;
            }
        }

        return null;
    }

    private function build_new_vs_returning_rows(?array $current, ?array $previous, ?string $current_label = null, ?string $previous_label = null): array
    {
        $rows = [];

        $current_new = $this->parse_numeric($this->array_value($current, ['newvsreturn', 'new']));
        $current_returning = $this->parse_numeric($this->array_value($current, ['newvsreturn', 'returning']));
        $previous_new = $this->parse_numeric($this->array_value($previous, ['newvsreturn', 'new']));
        $previous_returning = $this->parse_numeric($this->array_value($previous, ['newvsreturn', 'returning']));

        $current_label = $current_label ?? __('Current', 'mi-helper-reports');
        $previous_label = $previous_label ?? __('Previous', 'mi-helper-reports');
        $change_label = __('Change', 'mi-helper-reports');

        $rows[] = [
            'segment'  => __('New', 'mi-helper-reports'),
            $current_label  => $current_new !== null ? $this->format_percent_value($current_new) : '',
            $previous_label => $previous_new !== null ? $this->format_percent_value($previous_new) : '',
            $change_label   => $this->format_percent_delta($current_new, $previous_new),
        ];

        $rows[] = [
            'segment'  => __('Returning', 'mi-helper-reports'),
            $current_label  => $current_returning !== null ? $this->format_percent_value($current_returning) : '',
            $previous_label => $previous_returning !== null ? $this->format_percent_value($previous_returning) : '',
            $change_label   => $this->format_percent_delta($current_returning, $previous_returning),
        ];

        return $rows;
    }

    private function extract_metric_pair(?array $data, string $key): array
    {
        if ($data === null) {
            return ['', null];
        }

        if ($key === 'duration') {
            $display = $this->metric_display_value($data, 'duration') ?? '';
            $raw = $this->parse_duration_seconds($display);
            return [$display, $raw];
        }

        if ($key === 'bounce_rate') {
            $display = $this->metric_display_value($data, 'bounce_rate') ?? '';
            $raw = $this->parse_percentage($display);
            return [$display, $raw];
        }

        $display = $this->metric_display_value($data, $key) ?? '';
        $raw = $this->metric_raw_value($data, $key);

        return [$display, $raw];
    }

    private function format_number(float $value, int $precision = 0): string
    {
        return number_format($value, $precision);
    }

    private function format_percent_value(float $value, int $precision = 1): string
    {
        return number_format($value, $precision) . '%';
    }

    private function format_percent_delta(?float $current, ?float $previous): string
    {
        if ($current === null || $previous === null) {
            return '';
        }

        $delta = $current - $previous;
        if (abs($delta) < 0.0001) {
            return '0.0%';
        }

        return sprintf('%+0.1f%%', $delta);
    }

    private function transform_monthly_row(array $current, ?array $previous, string $start, string $end): array
    {
        $row = [
            'da_start' => $this->resolve_range_start($current, $start),
            'da_end'   => $this->resolve_range_end($current, $end),
            'nm_blog'  => $this->count_blog_posts($start, $end),
        ];

        $row['ses']        = $this->format_metric_value($current, 'sessions');
        $row['users_tot']  = $this->format_metric_value($current, 'totalusers');
        $row['users_new']  = $this->format_metric_value($current, 'new_users');
        $row['pg_vw']      = $this->format_metric_value($current, 'pageviews') ?: $this->format_metric_value($current, 'screenPageViews');
        $row['ses_chg_pct'] = $this->format_percent_change(
            $this->metric_raw_value($current, 'sessions'),
            $this->metric_raw_value($previous, 'sessions')
        );
        $row['pg_vw_chg_pct'] = $this->format_percent_change(
            $this->metric_raw_value($current, 'pageviews'),
            $this->metric_raw_value($previous, 'pageviews')
        );

        $row['engag_tot'] = $this->format_metric_value($current, 'engagedSessions');
        $row['eng_tot_chg'] = $this->format_percent_change(
            $this->metric_raw_value($current, 'engagedSessions'),
            $this->metric_raw_value($previous, 'engagedSessions')
        );

        $row['ses_dur_avg_s'] = $this->format_duration_seconds($this->metric_display_value($current, 'duration'));
        $row['ses_dur_avg_chg_pct'] = $this->format_percent_change(
            $this->parse_duration_seconds($this->metric_display_value($current, 'duration')),
            $this->parse_duration_seconds($this->metric_display_value($previous, 'duration'))
        );

        $row['bnc_rt_pct'] = $this->strip_percent($this->metric_display_value($current, 'bounce_rate'));
        $row['bnc_rt_chg_pct'] = $this->format_percent_change(
            $this->parse_percentage($this->metric_display_value($current, 'bounce_rate')),
            $this->parse_percentage($this->metric_display_value($previous, 'bounce_rate'))
        );

        $row['new_pct'] = $this->array_value($current, ['newvsreturn', 'new']);
        $row['ret_pct'] = $this->array_value($current, ['newvsreturn', 'returning']);

        $row['dsktp_pct'] = $this->array_value($current, ['devices', 'Desktop']);
        $row['tblt_pct']  = $this->array_value($current, ['devices', 'Tablet']);
        $row['mobl_pct']  = $this->array_value($current, ['devices', 'Mobile']);

        $this->inject_referrers($row, $current);
        $this->inject_top_pages($row, $current);

        return $row;
    }

    private function resolve_range_start(array $data, string $fallback): string
    {
        $start = $this->array_value($data, ['reportcurrentrange', 'startDate']);
        return $start ?: $fallback;
    }

    private function resolve_range_end(array $data, string $fallback): string
    {
        $end = $this->array_value($data, ['reportcurrentrange', 'endDate']);
        return $end ?: $fallback;
    }

    private function count_blog_posts(string $start, string $end): int
    {
        global $wpdb;

        $start_dt = $start . ' 00:00:00';
        $end_dt   = $end . ' 23:59:59';

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND post_date >= %s AND post_date <= %s",
                'post',
                $start_dt,
                $end_dt
            )
        );

        return (int) ($count ?? 0);
    }

    private function format_metric_value(?array $data, string $key): string
    {
        $value = $this->metric_display_value($data, $key);
        return $value !== null ? (string) $value : '';
    }

    private function metric_display_value(?array $data, string $key): ?string
    {
        if (!$data) {
            return null;
        }

        if (isset($data['infobox'][$key]['value'])) {
            return (string) $data['infobox'][$key]['value'];
        }

        if (isset($data[$key]) && is_scalar($data[$key])) {
            return (string) $data[$key];
        }

        return null;
    }

    private function metric_raw_value(?array $data, string $key): ?float
    {
        $value = $this->metric_display_value($data, $key);
        return $this->parse_numeric($value);
    }

    private function parse_numeric($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $value = str_replace([',', '%'], '', $value);
            $value = trim($value);
            if ($value === '') {
                return null;
            }
            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    private function format_percent_change(?float $current, ?float $previous): string
    {
        if ($current === null || $previous === null || abs($previous) < 0.00001) {
            return '';
        }

        $change = (($current - $previous) / $previous) * 100;
        return sprintf('%+0.1f%%', $change);
    }

    private function format_duration_seconds(?string $value): string
    {
        $seconds = $this->parse_duration_seconds($value);
        if ($seconds === null) {
            return '';
        }

        return (string) round($seconds);
    }

    private function parse_duration_seconds(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (preg_match('/^(\d+):(\d{2}):(\d{2})$/', $value, $matches)) {
            return (int) $matches[1] * 3600 + (int) $matches[2] * 60 + (int) $matches[3];
        }

        if (preg_match('/^(\d+):(\d{2})$/', $value, $matches)) {
            return (int) $matches[1] * 60 + (int) $matches[2];
        }

        if (preg_match_all('/(\d+)\s*h/', $value, $hours) && $hours[1]) {
            $total = 0;
            $total += (int) $hours[1][0] * 3600;
            if (preg_match('/(\d+)\s*m/', $value, $minutes)) {
                $total += (int) $minutes[1] * 60;
            }
            if (preg_match('/(\d+)\s*s/', $value, $seconds)) {
                $total += (int) $seconds[1];
            }
            return $total;
        }

        if (preg_match('/(\d+)\s*m/', $value, $minutes)) {
            $total = (int) $minutes[1] * 60;
            if (preg_match('/(\d+)\s*s/', $value, $seconds)) {
                $total += (int) $seconds[1];
            }
            return $total;
        }

        if (preg_match('/(\d+)\s*s/', $value, $seconds)) {
            return (int) $seconds[1];
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    private function strip_percent(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return rtrim($value, "% \t\n\r\0\x0B");
    }

    private function parse_percentage(?string $value): ?float
    {
        return $this->parse_numeric($value);
    }

    private function array_value(?array $data, array $path)
    {
        if (!$data) {
            return '';
        }
        $current = $data;
        foreach ($path as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return '';
            }
            $current = $current[$segment];
        }

        return is_scalar($current) ? (string) $current : '';
    }

    private function inject_referrers(array &$row, array $data): void
    {
        if (empty($data['referrals']) || !is_array($data['referrals'])) {
            return;
        }

        $refs = array_slice($data['referrals'], 0, 5);
        foreach ($refs as $index => $ref) {
            $num = $index + 1;
            $ref_url = $ref['url'] ?? ($ref['hostname'] ?? '');
            $row['ref_top_' . $num]    = $ref_url;
            $row['ref_top_' . $num . '_ct'] = isset($ref['sessions']) ? (string) $ref['sessions'] : '';
        }
    }

    private function inject_top_pages(array &$row, array $data): void
    {
        if (empty($data['toppages']) || !is_array($data['toppages'])) {
            return;
        }

        $pages = array_slice($data['toppages'], 0, 5);
        foreach ($pages as $index => $page) {
            $num = $index + 1;
            $row['pg_top_' . $num]    = $page['url'] ?? '';
            $row['pg_top_' . $num . '_ct'] = isset($page['sessions']) ? (string) $page['sessions'] : '';
        }
    }
}
