<footer>
    <div class="container">
        <div class="footer-content">
            <div class="footer-brand">
                <a href="{{ route('inicio') }}" class="logo">
                    <span class="logo-badge"><x-icon name="building" class="h-5 w-5" /></span>
                    Inmobiliaria García
                </a>
                <p>
                    Tu aliado de confianza para comprar, vender y arrendar propiedades en Neiva, Huila.
                    Más de 5 años de experiencia acompañando familias y empresas.
                </p>
            </div>

            <div>
                <h4>Servicios</h4>
                <ul class="footer-links">
                    <li><x-icon name="home" class="h-4 w-4" /> Compra de inmuebles</li>
                    <li><x-icon name="dollar-sign" class="h-4 w-4" /> Venta de propiedades</li>
                    <li><x-icon name="key" class="h-4 w-4" /> Arriendo residencial</li>
                    <li><x-icon name="building" class="h-4 w-4" /> Locales comerciales</li>
                </ul>
            </div>

            <div>
                <h4>Legal</h4>
                <ul class="footer-links">
                    <li><a href="#">Términos de uso</a></li>
                    <li><a href="#">Política de privacidad</a></li>
                    <li><a href="#">Tratamiento de datos</a></li>
                </ul>
            </div>

            <div>
                <h4>Contáctanos</h4>
                <ul class="footer-links">
                    <li><x-icon name="map-pin" class="h-4 w-4" /> Av. Principal #15-78, Neiva</li>
                    <li>
                        <a href="tel:+573138161568"><x-icon name="phone" class="h-4 w-4" /> 313 816 1568</a>
                    </li>
                    <li>
                        <a href="#" aria-label="Facebook"><x-icon name="facebook" class="h-4 w-4" /> Facebook</a>
                    </li>
                    <li>
                        <a href="#" aria-label="Instagram"><x-icon name="instagram" class="h-4 w-4" /> Instagram</a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            © {{ now()->year }} Inmobiliaria García · Todos los derechos reservados · Neiva, Huila
        </div>
    </div>
</footer>
