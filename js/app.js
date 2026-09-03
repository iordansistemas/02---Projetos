/**
 * Controlador Principal da Aplicação PWA (App Router & Global Handlers)
 */

const App = {
  currentView: 'dashboard',

  async init() {
    this.registerServiceWorker();
    await Auth.init();
    this.bindGlobalEvents();
    this.navigate('dashboard');
  },

  registerServiceWorker() {
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('./sw.js')
          .then(reg => console.log('PWA Service Worker registrado:', reg.scope))
          .catch(err => console.warn('Erro ao registrar Service Worker:', err));
      });
    }
  },

  bindGlobalEvents() {
    // Links de Navegação Sidebar e Mobile Tab Bar
    const navLinks = document.querySelectorAll('[data-view]');
    navLinks.forEach(link => {
      link.onclick = (e) => {
        e.preventDefault();
        const targetView = link.dataset.view;
        this.navigate(targetView);
      };
    });

    // Botão Login no Header
    const btnLoginHeader = document.getElementById('btn-header-login');
    if (btnLoginHeader) {
      btnLoginHeader.onclick = () => this.openLoginModal();
    }

    // Botão Logout no Badge
    const btnLogout = document.getElementById('btn-logout');
    if (btnLogout) {
      btnLogout.onclick = () => Auth.logout();
    }

    // Modal de Login - Submissão
    const formLogin = document.getElementById('form-login');
    if (formLogin) {
      formLogin.onsubmit = async (e) => {
        e.preventDefault();
        const reCpf = document.getElementById('login-re-cpf').value;
        const senha = document.getElementById('login-senha').value;
        try {
          const res = await Auth.login(reCpf, senha);
          this.showToast('Login realizado com sucesso!', 'success');
          this.closeLoginModal();
          if (Auth.isObservador()) {
            this.navigate('dashboard');
          } else {
            this.navigate(this.currentView);
          }
        } catch (err) {
          this.showToast(err.message || 'Credenciais inválidas.', 'danger');
        }
      };
    }


    // Handler de Câmera / Upload de Foto para Agraciados
    const photoFileInput = document.getElementById('photo-file-input');
    if (photoFileInput) {
      photoFileInput.onchange = (e) => {
        const file = e.target.files[0];
        if (file) {
          const reader = new FileReader();
          reader.onload = (event) => {
            document.getElementById('photo-preview').src = event.target.result;
            document.getElementById('foto-base64-input').value = event.target.result;
          };
          reader.readAsDataURL(file);
        }
      };
    }
  },

  navigate(viewName) {
    // Parar polling do dashboard se estiver trocando de tela
    if (this.currentView === 'dashboard' && viewName !== 'dashboard') {
      DashboardView.stopPolling();
    }

    // Perfil Observador possui acesso EXCLUSIVO ao Dashboard
    if (Auth.isObservador() && viewName !== 'dashboard') {
      this.showToast('O perfil de Observador possui acesso exclusivo ao Dashboard.', 'warning');
      this.navigate('dashboard');
      return;
    }

    // Proteção de rotas que exigem login
    if ((viewName === 'agraciados' || viewName === 'checkin' || viewName === 'checklist' || viewName === 'usuarios') && !Auth.isAuthenticated()) {
      this.showToast('Faça login para acessar esta seção.', 'warning');
      this.openLoginModal();
      return;
    }

    if (viewName === 'usuarios' && !Auth.isAdmin()) {
      this.showToast('Acesso negado. Requer perfil de Administrador.', 'danger');
      return;
    }


    this.currentView = viewName;

    // Atualiza links de navegação ativos
    document.querySelectorAll('[data-view]').forEach(link => {
      if (link.dataset.view === viewName) {
        link.classList.add('active');
      } else {
        link.classList.remove('active');
      }
    });

    // Exibe a seção correspondente
    document.querySelectorAll('.view-section').forEach(sec => {
      sec.classList.remove('active');
    });

    const targetSection = document.getElementById(`view-${viewName}`);
    if (targetSection) {
      targetSection.classList.add('active');
    }

    // Inicializa a View correspondente
    switch (viewName) {
      case 'dashboard':
        DashboardView.render();
        break;
      case 'agraciados':
        AgraciadosView.render();
        break;
      case 'checkin':
        CheckinView.render();
        break;
      case 'rsvp':
        RsvpView.bindEvents();
        break;
      case 'checklist':
        ChecklistView.render();
        break;
      case 'usuarios':
        UsuariosView.render();
        break;
    }
  },

  openLoginModal() {
    document.getElementById('modal-login').classList.add('active');
  },

  closeLoginModal() {
    document.getElementById('modal-login').classList.remove('active');
  },

  showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = 'toast';

    let icon = 'bi-info-circle-fill';
    if (type === 'success') icon = 'bi-check-circle-fill';
    if (type === 'warning') icon = 'bi-exclamation-triangle-fill';
    if (type === 'danger') icon = 'bi-x-circle-fill';

    toast.innerHTML = `<i class="bi ${icon}"></i> <span>${message}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateX(100%)';
      toast.style.transition = 'all 0.3s ease';
      setTimeout(() => toast.remove(), 300);
    }, 4000);
  }
};

// Inicializa a aplicação ao carregar a página
document.addEventListener('DOMContentLoaded', () => {
  App.init();
});
