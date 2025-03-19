/**
 * Archivo JavaScript principal para el Sistema de Listas de Regalos
 */

// Esperar a que el DOM esté cargado
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips de Bootstrap
    initTooltips();
    
    // Inicializar popovers de Bootstrap
    initPopovers();
    
    // Inicializar comportamiento de toasts
    initToasts();
    
    // Inicializar funcionalidad de copiar al portapapeles
    initClipboard();
    
    // Inicializar validación de formularios
    initFormValidation();
    
    // Inicializar confirmación de eliminación
    initDeleteConfirmation();
});

/**
 * Inicializa todos los tooltips de Bootstrap
 */
function initTooltips() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Inicializa todos los popovers de Bootstrap
 */
function initPopovers() {
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

/**
 * Inicializa la funcionalidad para los toasts
 */
function initToasts() {
    // Auto-mostrar toasts
    var toastElList = [].slice.call(document.querySelectorAll('.toast'));
    toastElList.map(function(toastEl) {
        return new bootstrap.Toast(toastEl, {
            autohide: true,
            delay: 5000
        }).show();
    });
    
    // Auto-ocultar alertas después de 5 segundos
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
}

/**
 * Inicializa la funcionalidad de copiar al portapapeles
 */
function initClipboard() {
    // Buscar todos los botones con la clase copy-button
    var copyButtons = document.querySelectorAll('.copy-button, [data-copy="true"]');
    
    copyButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            var textToCopy = '';
            var targetSelector = this.getAttribute('data-copy-target');
            
            // Si se especificó un selector para el texto a copiar
            if (targetSelector) {
                var targetElement = document.querySelector(targetSelector);
                if (targetElement) {
                    textToCopy = targetElement.value || targetElement.textContent;
                }
            } else {
                // Si el texto está en el atributo data-copy-text
                textToCopy = this.getAttribute('data-copy-text');
            }
            
            // Si hay texto para copiar
            if (textToCopy) {
                copyTextToClipboard(textToCopy, button);
            }
        });
    });
}

/**
 * Copia texto al portapapeles y muestra feedback
 */
function copyTextToClipboard(text, triggerElement) {
    // Crear un elemento temporal
    var tempElement = document.createElement('textarea');
    tempElement.value = text;
    tempElement.setAttribute('readonly', '');
    tempElement.style.position = 'absolute';
    tempElement.style.left = '-9999px';
    document.body.appendChild(tempElement);
    
    // Seleccionar y copiar el texto
    tempElement.select();
    var success = document.execCommand('copy');
    document.body.removeChild(tempElement);
    
    // Mostrar feedback
    if (success && triggerElement) {
        var originalText = triggerElement.innerHTML;
        var originalClass = triggerElement.className;
        
        // Cambiar apariencia temporalmente
        triggerElement.innerHTML = '<i class="bi bi-check"></i> Copiado';
        triggerElement.classList.add('btn-success');
        
        // Restaurar después de 2 segundos
        setTimeout(function() {
            triggerElement.innerHTML = originalText;
            triggerElement.className = originalClass;
        }, 2000);
    }
    
    return success;
}

/**
 * Inicializa validación personalizada de formularios
 */
function initFormValidation() {
    // Buscar todos los formularios con la clase needs-validation
    var forms = document.querySelectorAll('.needs-validation');
    
    // Bucle sobre ellos y evitar el envío
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
    
    // Validación de campos de contraseña
    var passwordInputs = document.querySelectorAll('input[data-match-password]');
    passwordInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            var targetId = this.getAttribute('data-match-password');
            var targetInput = document.getElementById(targetId);
            
            if (targetInput && this.value !== targetInput.value) {
                this.setCustomValidity('Las contraseñas no coinciden');
            } else {
                this.setCustomValidity('');
            }
        });
    });
}

/**
 * Inicializa confirmación para acciones de eliminación
 */
function initDeleteConfirmation() {
    // Buscar todos los formularios y enlaces con confirmación de eliminación
    var elements = document.querySelectorAll('[data-confirm]');
    
    elements.forEach(function(element) {
        element.addEventListener('click', function(event) {
            var message = this.getAttribute('data-confirm') || '¿Estás seguro de que deseas eliminar este elemento?';
            
            if (!confirm(message)) {
                event.preventDefault();
                event.stopPropagation();
            }
        });
    });
}

/**
 * Formatea un precio a moneda
 */
function formatCurrency(value, locale = 'es-MX', currency = 'MXN') {
    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: currency
    }).format(value);
}

/**
 * Crea una notificación toast dinámica
 */
function showToast(message, type = 'primary', title = 'Notificación') {
    // Crear estructura del toast
    var toastContainer = document.createElement('div');
    toastContainer.className = 'position-fixed bottom-0 end-0 p-3';
    toastContainer.style.zIndex = '11';
    
    var toastEl = document.createElement('div');
    toastEl.className = 'toast';
    toastEl.setAttribute('role', 'alert');
    toastEl.setAttribute('aria-live', 'assertive');
    toastEl.setAttribute('aria-atomic', 'true');
    
    var toastHeader = document.createElement('div');
    toastHeader.className = 'toast-header bg-' + type + ' text-white';
    
    var toastTitle = document.createElement('strong');
    toastTitle.className = 'me-auto';
    toastTitle.textContent = title;
    
    var toastCloseBtn = document.createElement('button');
    toastCloseBtn.type = 'button';
    toastCloseBtn.className = 'btn-close btn-close-white';
    toastCloseBtn.setAttribute('data-bs-dismiss', 'toast');
    toastCloseBtn.setAttribute('aria-label', 'Close');
    
    var toastBody = document.createElement('div');
    toastBody.className = 'toast-body';
    toastBody.textContent = message;
    
    // Ensamblar estructura
    toastHeader.appendChild(toastTitle);
    toastHeader.appendChild(toastCloseBtn);
    toastEl.appendChild(toastHeader);
    toastEl.appendChild(toastBody);
    toastContainer.appendChild(toastEl);
    
    // Añadir al documento
    document.body.appendChild(toastContainer);
    
    // Mostrar toast
    var toast = new bootstrap.Toast(toastEl, {
        autohide: true,
        delay: 5000
    });
    
    toast.show();
    
    // Eliminar del DOM cuando se oculte
    toastEl.addEventListener('hidden.bs.toast', function() {
        document.body.removeChild(toastContainer);
    });
}

/**
 * Funcionalidad para filtrar elementos en una lista
 */
function initFilter(inputSelector, itemsSelector, attributeToFilter = 'data-filter-text') {
    var filterInput = document.querySelector(inputSelector);
    
    if (!filterInput) return;
    
    filterInput.addEventListener('input', function() {
        var filterValue = this.value.toLowerCase().trim();
        var items = document.querySelectorAll(itemsSelector);
        
        items.forEach(function(item) {
            var filterText = item.getAttribute(attributeToFilter) || item.textContent;
            filterText = filterText.toLowerCase();
            
            if (filterText.includes(filterValue)) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    });
}

/**
 * Inicializa elementos dinámicos añadidos al DOM después de cargar la página
 */
function initDynamicElements() {
    // Reinicializar tooltips y popovers
    initTooltips();
    initPopovers();
    
    // Reinicializar otros componentes si es necesario
}