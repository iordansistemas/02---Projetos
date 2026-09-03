/**
 * Módulo de Integração com a API Backend
 * Trata requisições HTTP, headers de autenticação, tratamento de erros e polling em tempo real
 */

const API_BASE = './api';

const API = {
  /**
   * Executa requisição generica
   */
  async request(endpoint, options = {}) {
    const url = `${API_BASE}/${endpoint}`;
    const defaultHeaders = {};

    if (!(options.body instanceof FormData)) {
      defaultHeaders['Content-Type'] = 'application/json';
    }

    const config = {
      credentials: 'same-origin',
      ...options,
      headers: {
        ...defaultHeaders,
        ...options.headers
      }
    };


    try {
      const response = await fetch(url, config);
      const rawText = await response.text();
      let data = {};
      try {
        data = JSON.parse(rawText);
      } catch (jsonErr) {
        console.error(`[API Error Raw Text] ${endpoint}:`, rawText);
        throw new Error("Erro no servidor. Verifique se está logado ou tente novamente.");
      }
      
      if (!response.ok && !data.success) {
        throw new Error(data.message || `Erro HTTP ${response.status}`);
      }
      return data;
    } catch (error) {
      console.error(`[API Error] ${endpoint}:`, error);
      throw error;
    }
  },


  // Autenticação
  login: (re_cpf, senha) => API.request('auth.php?action=login', {
    method: 'POST',
    body: JSON.stringify({ re_cpf, senha })
  }),

  logout: () => API.request('auth.php?action=logout', { method: 'POST' }),
  
  checkAuthStatus: () => API.request('auth.php?action=status'),

  // Agraciados
  getAgraciados: (params = {}) => {
    const query = new URLSearchParams(params).toString();
    return API.request(`agraciados.php?${query}`);
  },

  getAgraciadoById: (id) => API.request(`agraciados.php?id=${id}`),

  saveAgraciadoFormData: (formData) => API.request('agraciados.php', {
    method: 'POST',
    body: formData
  }),

  deleteAgraciado: (id) => API.request('agraciados.php', {
    method: 'DELETE',
    body: JSON.stringify({ id })
  }),

  // Check-in no Dia do Evento
  getPresenceFeed: (limit = 50) => API.request(`checkin.php?limit=${limit}`),

  getAllPresenceList: () => API.request('checkin.php?only_present=0'),

  toggleCheckin: (id, presente) => API.request('checkin.php', {
    method: 'POST',
    body: JSON.stringify({ id, presente })
  }),

  // RSVP / Ciência Pública do Agraciado
  searchRSVP: (termo) => API.request(`rsvp.php?termo=${encodeURIComponent(termo)}`),

  confirmRSVP: (id, confirmou) => API.request('rsvp.php', {
    method: 'POST',
    body: JSON.stringify({ id, confirmou })
  }),

  // Checklist Operacional & Resultados
  getChecklist: (params = {}) => {
    const query = new URLSearchParams(params).toString();
    return API.request(`checklist.php?${query}`);
  },

  saveChecklistAction: (taskData) => API.request('checklist.php', {
    method: 'POST',
    body: JSON.stringify(taskData)
  }),

  deleteChecklistAction: (id) => API.request('checklist.php', {
    method: 'DELETE',
    body: JSON.stringify({ id })
  }),

  // Usuários & Privilégios (RBAC)
  getUsuarios: () => API.request('usuarios.php'),

  saveUsuario: (userData) => API.request('usuarios.php', {
    method: 'POST',
    body: JSON.stringify(userData)
  }),

  deleteUsuario: (id) => API.request('usuarios.php', {
    method: 'DELETE',
    body: JSON.stringify({ id })
  })
};
