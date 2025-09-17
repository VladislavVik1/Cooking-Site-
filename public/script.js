document.addEventListener('DOMContentLoaded', function () {
    
    const navToggle = document.querySelector('.nav-toggle');
    const navMenu = document.querySelector('.nav-menu');
  
    if (navToggle && navMenu) {
      navToggle.addEventListener('click', function () {
        navMenu.classList.toggle('active');
        navToggle.classList.toggle('active');
      });
    }
  
    
    document.querySelectorAll('.nav-link').forEach(link => {
      link.addEventListener('click', function () {
        if (navMenu) navMenu.classList.remove('active');
        if (navToggle) navToggle.classList.remove('active');
      });
    });
  
    
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function (e) {
        const targetId = this.getAttribute('href');
        if (targetId === '#') return;
        const target = document.querySelector(targetId);
        if (target) {
          e.preventDefault();
          window.scrollTo({
            top: target.offsetTop - 70,
            behavior: 'smooth'
          });
        }
      });
    });
  
    
    const modal = document.getElementById('recipeModal');
    const modalBody = document.getElementById('modalBody');
    const closeModal = document.querySelector('.close');
  
    function openModal(html) {
      if (!modal || !modalBody) return;
      modalBody.innerHTML = html;
      modal.style.display = 'block';
      animateModalContent();
    }
    function hideModal() {
      if (modal) modal.style.display = 'none';
    }
  
    document.querySelectorAll('.recipe-btn').forEach(btn => {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        if (!modal || !modalBody) return; 
        const recipeId = this.getAttribute('data-id');
        if (!recipeId) return;
  
        const xhr = new XMLHttpRequest();
        xhr.open('GET', `get_recipe.php?id=${encodeURIComponent(recipeId)}`, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function () {
          if (xhr.status === 200) openModal(xhr.responseText);
          else openModal('<p>Произошла ошибка при загрузке рецепта.</p>');
        };
        xhr.onerror = function () {
          openModal('<p>Произошла ошибка при загрузке рецепта.</p>');
        };
        xhr.send();
      });
    });
  
    if (closeModal) closeModal.addEventListener('click', hideModal);
    window.addEventListener('click', function (e) {
      if (modal && e.target === modal) hideModal();
    });
    window.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') hideModal();
    });
  

    function animateModalContent() {
      if (!modalBody) return;
      const elements = modalBody.querySelectorAll('h2, p, ul, img, div');
      elements.forEach((el, i) => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity .5s ease, transform .5s ease';
        setTimeout(() => {
          el.style.opacity = '1';
          el.style.transform = 'translateY(0)';
        }, 100 + i * 100);
      });
    }
  

    const animated = document.querySelectorAll('.recipe-card, .category-card, .about-content, .search-form');
    function prepAnimated(el) {
      el.style.opacity = '0';
      el.style.transform = 'translateY(20px)';
      el.style.transition = 'opacity .6s ease, transform .6s ease';
    }
    function checkScroll() {
      const wh = window.innerHeight;
      const sy = window.scrollY || window.pageYOffset;
      animated.forEach(el => {
        const top = el.getBoundingClientRect().top + sy;
        if (sy + wh - 150 > top) {
          el.style.opacity = '1';
          el.style.transform = 'translateY(0)';
        }
      });
    }
    animated.forEach(prepAnimated);
    window.addEventListener('scroll', checkScroll, { passive: true });
    window.addEventListener('load', checkScroll);
  

    const searchInput = document.querySelector('input[name="search"]');
    let searchTimeout;
    if (searchInput) {
      searchInput.addEventListener('input', function () {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
          if (this.value.length >= 3 || this.value.length === 0) {
            if (this.form) this.form.submit();
          }
        }, 500);
      });
    }
  

    const statsSection = document.querySelector('.about-stats');
    const statNumbers = document.querySelectorAll('.stat-number');
    let statsAnimated = false;
    function animateStats() {
      if (statsAnimated || !statsSection) return;
      const wh = window.innerHeight;
      const sy = window.scrollY || window.pageYOffset;
      const top = statsSection.getBoundingClientRect().top + sy;
      if (sy + wh > top + 100) {
        statsAnimated = true;
        statNumbers.forEach(stat => {
          const target = parseInt(stat.textContent, 10) || 0;
          let current = 0;
          const inc = Math.max(1, Math.ceil(target / 50));
          const t = setInterval(() => {
            current += inc;
            if (current >= target) {
              current = target;
              clearInterval(t);
            }
            stat.textContent = current + '+';
          }, 30);
        });
      }
    }
    if (statsSection) {
      window.addEventListener('scroll', animateStats, { passive: true });
      window.addEventListener('load', animateStats);
    }
  

    const hero = document.querySelector('.hero');
    if (hero) {
      window.addEventListener('scroll', function () {
        const rate = (window.pageYOffset || 0) * -0.5;
        hero.style.backgroundPosition = `center ${rate}px`;
      }, { passive: true });
    }
  
    
    const addIngredientBtn = document.getElementById('addIngredient');
    const ingredientsContainer = document.getElementById('ingredientsContainer');
  
    if (addIngredientBtn && ingredientsContainer) {
      const attachRemove = (row) => {
        const btn = row.querySelector('.remove-ingredient');
        if (btn) btn.addEventListener('click', () => row.remove());
      };
  
      addIngredientBtn.addEventListener('click', function () {
        const row = document.createElement('div');
        row.className = 'ingredient-row';
        row.innerHTML = `
          <input type="text" name="ingredient_names[]" placeholder="Название ингредиента">
          <input type="text" name="quantities[]" placeholder="Количество">
          <select name="units[]">
            <option value="г">г</option><option value="кг">кг</option>
            <option value="мл">мл</option><option value="л">л</option>
            <option value="шт">шт</option><option value="ч.л.">ч.л.</option>
            <option value="ст.л.">ст.л.</option><option value="стакан">стакан</option>
            <option value="щепотка">щепотка</option><option value="по вкусу">по вкусу</option>
          </select>
          <button type="button" class="remove-ingredient">Удалить</button>`;
        ingredientsContainer.appendChild(row);
        attachRemove(row);
      });
  
      ingredientsContainer.querySelectorAll('.remove-ingredient').forEach(btn => {
        btn.addEventListener('click', () => btn.parentElement.remove());
      });
    }
  

    const validationMessages = {
      valueMissing: 'Это поле обязательно для заполнения.',
      typeMismatch: {
        email: 'Пожалуйста, введите корректный email адрес.',
        url: 'Пожалуйста, введите корректный URL.'
      }
    };
  
    document.querySelectorAll('form').forEach(form => {
      form.addEventListener('submit', function (e) {
        let valid = true;
        this.querySelectorAll('input[required], textarea[required], select[required]').forEach(input => {
          if (!input.value.trim()) {
            valid = false;
            input.style.borderColor = 'red';
            input.addEventListener('input', function () { this.style.borderColor = ''; }, { once: true });
          }
        });
        if (!valid) {
          e.preventDefault();
          alert('Пожалуйста, заполните все обязательные поля.');
        }
      });
    });
  

    document.querySelectorAll('input, textarea, select').forEach(input => {
      input.addEventListener('invalid', function (e) {
        e.preventDefault();
        let message = '';
        if (this.validity.valueMissing) {
          message = validationMessages.valueMissing;
        } else if (this.validity.typeMismatch) {
          message = validationMessages.typeMismatch[this.type] || 'Неверный формат.';
        }
        if (message) {
          this.setCustomValidity(message);
          this.reportValidity();
          this.setCustomValidity('');
        }
      });
      input.addEventListener('input', function () {
        this.setCustomValidity('');
      });
    });
  
    if ('IntersectionObserver' in window) {
      const lazy = document.querySelectorAll('img[data-src]');
      const io = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            const img = entry.target;
            img.src = img.dataset.src;
            img.removeAttribute('data-src');
            io.unobserve(img);
          }
        });
      });
      lazy.forEach(img => io.observe(img));
    }
  

    const darkModeToggle = document.createElement('button');
    darkModeToggle.innerHTML = '<i class="fas fa-moon"></i>';
    darkModeToggle.classList.add('dark-mode-toggle');
    Object.assign(darkModeToggle.style, {
      position: 'fixed', bottom: '20px', right: '20px', zIndex: '1000',
      width: '50px', height: '50px', borderRadius: '50%', background: '#333',
      color: '#fff', border: 'none', cursor: 'pointer', boxShadow: '0 2px 10px rgba(0,0,0,.2)'
    });
    document.body.appendChild(darkModeToggle);
  
    if (localStorage.getItem('darkMode') === 'enabled') {
      document.body.classList.add('dark-mode');
      darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>';
      darkModeToggle.style.background = '#fff';
      darkModeToggle.style.color = '#333';
    }
  
    darkModeToggle.addEventListener('click', function () {
      document.body.classList.toggle('dark-mode');
      const enabled = document.body.classList.contains('dark-mode');
      if (enabled) {
        localStorage.setItem('darkMode', 'enabled');
        this.innerHTML = '<i class="fas fa-sun"></i>';
        this.style.background = '#fff';
        this.style.color = '#333';
      } else {
        localStorage.removeItem('darkMode'); 
        this.innerHTML = '<i class="fas fa-moon"></i>';
        this.style.background = '#333';
        this.style.color = '#fff';
      }
    });
  });
  