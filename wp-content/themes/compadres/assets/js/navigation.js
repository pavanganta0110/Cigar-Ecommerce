const toggle = document.querySelector('.nav-toggle');
const navigation = document.querySelector('#primary-navigation');

if (toggle && navigation) {
  const closeNavigation = ({ restoreFocus = false } = {}) => {
    toggle.setAttribute('aria-expanded', 'false');
    navigation.classList.remove('is-open');
    document.body.classList.remove('navigation-open');
    if (restoreFocus) toggle.focus();
  };

  toggle.addEventListener('click', () => {
    const expanded = toggle.getAttribute('aria-expanded') === 'true';
    toggle.setAttribute('aria-expanded', String(!expanded));
    navigation.classList.toggle('is-open', !expanded);
    document.body.classList.toggle('navigation-open', !expanded);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
      closeNavigation({ restoreFocus: true });
    }
  });

  document.addEventListener('click', (event) => {
    if (
      toggle.getAttribute('aria-expanded') === 'true' &&
      !navigation.contains(event.target) &&
      !toggle.contains(event.target)
    ) {
      closeNavigation();
    }
  });

  window.addEventListener('resize', () => {
    if (window.matchMedia('(min-width: 801px)').matches) closeNavigation();
  });
}
