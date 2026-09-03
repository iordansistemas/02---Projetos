/**
 * Módulo de Controle de Autenticação e Permissões do Usuário
 */

const Auth = {
  currentUser: null,

  async init() {
    try {
      const res = await API.checkAuthStatus();
      if (res.authenticated) {
        this.currentUser = res.usuario;
        this.updateUI();
        return true;
      } else {
        this.currentUser = null;
        this.updateUI();
        return false;
      }
    } catch (err) {
      console.warn("Falha ao verificar status de autenticação:", err);
      this.currentUser = null;
      this.updateUI();
      return false;
    }
  },

  isAuthenticated() {
    return this.currentUser !== null;
  },

  isAdmin() {
    return this.currentUser && this.currentUser.funcao && this.currentUser.funcao.toUpperCase() === 'ADMIN';
  },

  isOrganizador() {
    return this.currentUser && this.currentUser.funcao && (this.currentUser.funcao.toUpperCase() === 'ORGANIZADOR' || this.currentUser.funcao.toUpperCase() === 'ADMIN');
  },

  isObservador() {
    return this.currentUser && this.currentUser.funcao && this.currentUser.funcao.toUpperCase() === 'OBSERVADOR';
  },

  async login(re_cpf, senha) {
    const res = await API.login(re_cpf, senha);
    if (res.success) {
      this.currentUser = res.usuario;
      this.updateUI();
      return res;
    }
    throw new Error(res.message || "Falha ao realizar login.");
  },

  async logout() {
    await API.logout();
    this.currentUser = null;
    this.updateUI();
    window.location.reload();
  },

  updateUI() {
    const userBadge = document.getElementById('user-badge');
    const userNameEl = document.getElementById('user-name');
    const userRoleEl = document.getElementById('user-role');
    const loginBtnHeader = document.getElementById('btn-header-login');

    // Limpa classes de perfil do body
    document.body.classList.remove('role-admin', 'role-organizador', 'role-observador');

    if (this.currentUser) {
      if (userBadge) userBadge.style.display = 'flex';
      if (loginBtnHeader) loginBtnHeader.style.display = 'none';
      if (userNameEl) userNameEl.textContent = this.currentUser.nome;
      
      const roleUpper = (this.currentUser.funcao || '').toUpperCase();
      let roleLabel = 'Organizador';
      if (roleUpper === 'ADMIN') roleLabel = 'Administrador';
      if (roleUpper === 'OBSERVADOR') roleLabel = 'Observador';
      
      if (userRoleEl) userRoleEl.textContent = roleLabel;

      if (this.isObservador()) {
        // Aplica classe CSS que oculta todos os outros menus e telas
        document.body.classList.add('role-observador');
        
        // Fecha modais abertos
        document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));

        // Força exibição exclusiva do Dashboard
        if (typeof App !== 'undefined') {
          App.currentView = 'dashboard';
          document.querySelectorAll('.view-section').forEach(sec => sec.classList.remove('active'));
          const dash = document.getElementById('view-dashboard');
          if (dash) dash.classList.add('active');
          if (typeof DashboardView !== 'undefined') DashboardView.render();
        }
      } else if (this.isAdmin()) {
        document.body.classList.add('role-admin');
        document.querySelectorAll('[data-view]').forEach(link => link.style.display = 'flex');
      } else {
        document.body.classList.add('role-organizador');
        document.querySelectorAll('[data-view]').forEach(link => {
          link.style.display = (link.dataset.view === 'usuarios') ? 'none' : 'flex';
        });
      }
    } else {
      if (userBadge) userBadge.style.display = 'none';
      if (loginBtnHeader) loginBtnHeader.style.display = 'flex';

      // Usuário não logado enxerga Dashboard e Ciência (RSVP)
      document.querySelectorAll('[data-view]').forEach(link => {
        const view = link.dataset.view;
        link.style.display = (view === 'dashboard' || view === 'rsvp') ? 'flex' : 'none';
      });
    }
  }




};
