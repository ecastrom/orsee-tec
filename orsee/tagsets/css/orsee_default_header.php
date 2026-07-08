<!-- part of orsee. see orsee.org -- customized for the BEER Lab (Tec de Monterrey) -->
<style>
/* BEER Lab masthead: logo + hairline divider + title lockup, thin Tec-blue accent. */
.orsee-masthead {
    /* kill the default diagonal gradient: clean flat white with a subtle shadow */
    background: #ffffff;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
    border-bottom: 3px solid #0039a6;
}
.orsee .orsee-masthead .orsee-brand {
    align-items: center;
    justify-content: flex-start;
    gap: 0.9rem;
    padding: 0.55rem 1rem;
}
.orsee .orsee-masthead .orsee-brand-left { position: static; transform: none; flex: 0 0 auto; padding: 0; }
.orsee .orsee-masthead .orsee-brand-bar { display: none; }

/* the lockup never forces horizontal overflow: it flexes and ellipsizes */
.beer-brand { display: flex; align-items: center; gap: 1rem; flex: 1 1 auto; min-width: 0; }
.beer-brand-logo { flex: 0 0 auto; }
.beer-brand-logo img {
    display: block;
    height: 44px;
    width: auto;
    margin: 0 !important;
}
.beer-brand-divider {
    width: 1px;
    height: 40px;
    background: #d4d9e2;
    flex: 0 0 auto;
}
.beer-brand-titles { display: flex; flex-direction: column; justify-content: center; min-width: 0; }
.beer-brand-title {
    font-size: 1.3rem;
    font-weight: 700;
    line-height: 1.15;
    color: #0039a6;
    letter-spacing: 0.01em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.beer-brand-subtitle {
    font-size: 0.68rem;
    font-weight: 500;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #5c6675;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
/* keep the admin area's own spacing conventions intact */
.orsee.orsee-area-admin .beer-brand-logo img { height: 40px; }

@media (max-width: 700px) {
    .beer-brand-subtitle { display: none; }
    .beer-brand-logo img { height: 32px; }
    .beer-brand-divider { height: 28px; }
    .beer-brand-title { font-size: 1.05rem; }
    .orsee .orsee-masthead .orsee-brand { gap: 0.6rem; }
    .beer-brand { gap: 0.6rem; }
}
@media (max-width: 480px) {
    .beer-brand-divider { display: none; }
    .beer-brand-logo img { height: 26px; }
}
</style>
<header class="orsee-masthead">
    <div class="orsee-brand">
        <div class="orsee-brand-left">
            <button class="orsee-burger" type="button" aria-label="Toggle menu"></button>
        </div>
        <div class="beer-brand">
            <div class="beer-brand-logo"><img src="../tagsets/css/orsee3_logo.png" alt="Tecnológico de Monterrey"></div>
            <div class="beer-brand-divider"></div>
            <div class="beer-brand-titles">
                <div class="beer-brand-title">BEER Lab</div>
                <div class="beer-brand-subtitle">Behavioral &amp; Experimental Economics Research Lab</div>
            </div>
        </div>
    </div>
</header>
