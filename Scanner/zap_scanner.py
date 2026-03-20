print("RUNNING SCANNER FILE", flush=True)
import requests
import time
import sys
from pathlib import Path

ZAP_API = 'http://localhost:8080'
API_KEY = 'j9u08qi9sjf2b759acqeg79vq7'

REQUEST_TIMEOUT = 30
PROJECT_ROOT = Path(__file__).resolve().parent.parent
REPORTS_DIR = PROJECT_ROOT / 'storage' / 'app' / 'reports'
LARAVEL_PROGRESS_URL = 'http://127.0.0.1:8000/api/update-progress'
POST_TIMEOUT = 10


def safe_request(url, params=None):
    return requests.get(url, params=params, timeout=REQUEST_TIMEOUT)


def post_progress(scan_record_id, progress, phase, status=None):
    if not scan_record_id:
        return

    payload = {
        'scan_id': int(scan_record_id),
        'progress': int(progress),
        'phase': phase,
    }

    if status is not None:
        payload['status'] = status

    try:
        resp = requests.post(LARAVEL_PROGRESS_URL, json=payload, timeout=POST_TIMEOUT)
        resp.raise_for_status()
    except Exception as e:
        print(f"Warning: could not send progress update to Laravel: {e}", flush=True)


def run_spider(target_url):
    spider_url = f"{ZAP_API}/JSON/spider/action/scan/"
    params = {'url': target_url}
    if API_KEY:
        params['apikey'] = API_KEY

    try:
        resp = safe_request(spider_url, params=params)
        resp.raise_for_status()
        scan_id = resp.json().get('scan')
        print(f"Started spider. Scan ID: {scan_id}", flush=True)
        return scan_id
    except requests.exceptions.ConnectionError:
        print(f"Error: Cannot reach ZAP at {ZAP_API}", flush=True)
        print("Start OWASP ZAP and enable the API.", flush=True)
        sys.exit(1)
    except Exception as e:
        print(f"Error starting spider: {e}", flush=True)
        print("Spider start failed.", flush=True)
        sys.exit(1)


def get_discovered_urls(target_url):
    urls_api = f"{ZAP_API}/JSON/core/view/urls/"
    params = {}
    if API_KEY:
        params['apikey'] = API_KEY

    try:
        resp = safe_request(urls_api, params=params)
        resp.raise_for_status()
        urls = resp.json().get('urls', [])

        filtered_urls = []
        target_variants = {
            target_url,
            target_url.rstrip('/'),
            target_url.rstrip('/') + '/',
        }

        for url in urls:
            if any(url.startswith(variant.rstrip('/')) for variant in target_variants):
                filtered_urls.append(url)

        print(f"All URLs in scan tree: {urls}", flush=True)
        print(f"Filtered discovered URLs: {filtered_urls}", flush=True)
        return filtered_urls
    except Exception as e:
        print(f"Error retrieving discovered URLs: {e}", flush=True)
        return []


def access_target_url(target_url):
    access_url = f"{ZAP_API}/JSON/core/action/accessUrl/"
    params = {
        'url': target_url,
        'followRedirects': 'true',
    }
    if API_KEY:
        params['apikey'] = API_KEY

    try:
        resp = safe_request(access_url, params=params)
        resp.raise_for_status()
        print(f"Accessed target URL: {target_url}", flush=True)
    except Exception as e:
        print(f"Error accessing target URL: {e}", flush=True)


def wait_for_spider(scan_id, scan_record_id=None):
    status_url = f"{ZAP_API}/JSON/spider/view/status/"
    params = {'scanId': scan_id}
    if API_KEY:
        params['apikey'] = API_KEY

    print("Waiting for spider to complete...", flush=True)

    while True:
        try:
            resp = safe_request(status_url, params=params)
            resp.raise_for_status()
            status = int(resp.json().get('status', 0))
            print(f"Spider progress: {status}%", flush=True)
            post_progress(scan_record_id, round(status * 0.5), 'Spidering', 'running')

            if status >= 100:
                print("Spider completed.", flush=True)
                break

            time.sleep(2)
        except Exception as e:
            print(f"Error checking spider status: {e}", flush=True)
            print("Spider status check failed.", flush=True)
            sys.exit(1)


def start_active_scan(target_url):
    scan_url = f"{ZAP_API}/JSON/ascan/action/scan/"
    print("Using updated start_active_scan()", flush=True)

    candidates = [
        target_url,
        target_url.rstrip('/'),
        target_url.rstrip('/') + '/',
    ]

    discovered_urls = get_discovered_urls(target_url)
    for discovered in discovered_urls:
        if discovered not in candidates:
            candidates.append(discovered)

    candidates = list(dict.fromkeys(candidates))

    for candidate in candidates:
        params = {'url': candidate, 'recurse': 'true'}
        if API_KEY:
            params['apikey'] = API_KEY

        try:
            print(f"Trying active scan on: {candidate}", flush=True)
            resp = safe_request(scan_url, params=params)
            print(f"Active scan status code: {resp.status_code}", flush=True)
            print(f"Active scan raw response: {resp.text}", flush=True)

            if resp.status_code >= 400:
                print(f"Active scan rejected for {candidate}", flush=True)
                continue

            scan_id = resp.json().get('scan')
            print(f"Started active scan. Scan ID: {scan_id}", flush=True)
            return scan_id
        except requests.exceptions.ConnectionError:
            print(f"Error starting scan: Cannot reach ZAP at {ZAP_API}", flush=True)
            print("Start OWASP ZAP and enable the API.", flush=True)
            sys.exit(1)
        except Exception as e:
            print(f"Error starting scan for {candidate}: {e}", flush=True)

    print("Active scan start failed.", flush=True)
    print("Error: ZAP rejected all active scan candidates.", flush=True)
    sys.exit(1)


def wait_for_scan(scan_id, scan_record_id=None):
    status_url = f"{ZAP_API}/JSON/ascan/view/status/"
    params = {'scanId': scan_id}
    if API_KEY:
        params['apikey'] = API_KEY

    print("Waiting for scan to complete...", flush=True)

    while True:
        try:
            resp = safe_request(status_url, params=params)
            resp.raise_for_status()
            status = int(resp.json().get('status', 0))
            print(f"Scan progress: {status}%", flush=True)
            post_progress(scan_record_id, 50 + round(status * 0.5), 'Active Scan', 'running')

            if status >= 100:
                print("Scan completed.", flush=True)
                break

            time.sleep(5)
        except Exception as e:
            print(f"Error checking scan status: {e}", flush=True)
            print("Active scan status check failed.", flush=True)
            sys.exit(1)


def get_alerts(target_url):
    alerts_url = f"{ZAP_API}/JSON/alert/view/alerts/"
    params = {'baseurl': target_url}
    if API_KEY:
        params['apikey'] = API_KEY

    try:
        resp = safe_request(alerts_url, params=params)
        resp.raise_for_status()
        return resp.json().get('alerts', [])
    except Exception as e:
        print(f"Error retrieving alerts: {e}", flush=True)
        return []


def print_alert_summary(alerts):
    summary = {}
    for alert in alerts:
        risk = alert.get('risk', 'Unknown')
        summary[risk] = summary.get(risk, 0) + 1

    print("\nAlert Summary:", flush=True)
    for risk, count in summary.items():
        print(f"  {risk}: {count}", flush=True)
    print(f"Total alerts: {len(alerts)}", flush=True)


def save_html_report(filename='zap_report.html'):
    report_url = f"{ZAP_API}/OTHER/core/other/htmlreport/"
    params = {}
    if API_KEY:
        params['apikey'] = API_KEY

    try:
        REPORTS_DIR.mkdir(parents=True, exist_ok=True)
        report_path = REPORTS_DIR / filename
        resp = safe_request(report_url, params=params)
        resp.raise_for_status()
        with open(report_path, 'w', encoding='utf-8') as f:
            f.write(resp.text)
        print(f"HTML report saved to {report_path}", flush=True)
    except Exception as e:
        print(f"Error saving HTML report: {e}", flush=True)
        sys.exit(1)


def save_json_report(filename='zap_report.json'):
    report_url = f"{ZAP_API}/JSON/core/view/alerts/"
    params = {}
    if API_KEY:
        params['apikey'] = API_KEY

    try:
        REPORTS_DIR.mkdir(parents=True, exist_ok=True)
        report_path = REPORTS_DIR / filename
        resp = safe_request(report_url, params=params)
        resp.raise_for_status()
        with open(report_path, 'w', encoding='utf-8') as f:
            f.write(resp.text)
        print(f"JSON report saved to {report_path}", flush=True)
    except Exception as e:
        print(f"Error saving JSON report: {e}", flush=True)
        sys.exit(1)


def main():
    print("ZAP scanner script started - VERSION 2", flush=True)

    if len(sys.argv) not in (2, 3):
        print(f"Usage: python {sys.argv[0]} <target_url> [scan_record_id]", flush=True)
        sys.exit(1)

    target_url = sys.argv[1].strip()
    scan_record_id = sys.argv[2].strip() if len(sys.argv) == 3 else None

    print(f"Target URL: {target_url}", flush=True)
    print(f"Reports directory: {REPORTS_DIR}", flush=True)
    print(f"Scan record ID: {scan_record_id}", flush=True)

    post_progress(scan_record_id, 0, 'Starting', 'running')

    spider_id = run_spider(target_url)
    if spider_id is None:
        print("Error: Spider did not return a scan ID.", flush=True)
        sys.exit(1)

    wait_for_spider(spider_id, scan_record_id)
    access_target_url(target_url)
    time.sleep(2)

    scan_id = start_active_scan(target_url)
    if scan_id is None:
        print("Error: Active scan did not return a scan ID.", flush=True)
        sys.exit(1)

    wait_for_scan(scan_id, scan_record_id)
    alerts = get_alerts(target_url)
    print_alert_summary(alerts)
    save_html_report()
    save_json_report()
    post_progress(scan_record_id, 100, 'Completed', 'completed')
    print("ZAP scan finished successfully", flush=True)


if __name__ == '__main__':
    main()