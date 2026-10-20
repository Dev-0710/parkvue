/**
 * api.js — ParkVue API Service
 * Clean and compact Fetch wrapper for backend PHP/Node endpoints.
 */
const ParkVueAPI = (() => {
  const BASE = 'api/';
  let _errorHandler = null;

  async function request(endpoint, method = 'GET', data = null) {
    const options = {
      method,
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }
    };
    if (data && method !== 'GET') options.body = JSON.stringify(data);

    try {
      const res = await fetch(BASE + endpoint, options);
      const json = await res.json();
      if (!res.ok || json.status === 'error') {
        const err = json.message || `Error ${res.status}`;
        if (_errorHandler) _errorHandler(err);
        return { status: 'error', message: err };
      }
      return json;
    } catch (e) {
      const err = e.message || 'Network connection error';
      if (_errorHandler) _errorHandler(err);
      return { status: 'error', message: err };
    }
  }

  return {
    onError: (fn) => { _errorHandler = fn; },
    getTerminalState: () => request('terminal_state.php'),
    entryCheckin: (payload) => request('entry_checkin.php', 'POST', payload),
    exitCheckout: (query) => request(`exit_checkout.php?query=${encodeURIComponent(query)}`),
    settlePayment: (payload) => request('settle_payment.php', 'POST', payload),
    manualOverride: (reason = 'Manual Emergency Release') => request('manual_override.php', 'POST', { reason }),
    getShiftReport: () => request('shift_report.php')
  };
})();
