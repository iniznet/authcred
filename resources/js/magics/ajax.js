import ajax from '../utils/ajax.js';

export default async function (data, returnJson = true) {
  const response = await ajax('//' + window.location.host + '/wp-admin/admin-ajax.php', data, returnJson);
  return response;
}
