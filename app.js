(function () {
  const meals = window.GOOD_MEALS || [];
  const prefersReduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const mealPage = document.querySelector("[data-meal-page]");
  const assetPrefix = mealPage ? "../" : "";
  const mealVisualClass = (meal, baseClass) => meal.image ? `${baseClass} meal-photo` : `${baseClass} meal-crop crop-${meal.crop}`;
  const mealVisualStyle = (meal) => meal.image ? ` style="background-image: url('${assetPrefix}${meal.image}')"` : "";

  document.documentElement.classList.add("is-ready");

  const grid = document.querySelector("#featuredMeals");
  if (grid) {
    grid.innerHTML = meals.map((meal) => `
      <article class="meal-card" data-reveal>
        <a class="${mealVisualClass(meal, "meal-card__image")}" href="meals/${meal.slug}.html" aria-label="View ${meal.name}"${mealVisualStyle(meal)}></a>
        <div class="meal-card__body">
          <div class="label-row">${meal.labels.map((label) => `<span>${label}</span>`).join("")}</div>
          <h3>${meal.name}</h3>
          <p>${meal.description}</p>
          <div class="macro-row">
            <span>${meal.calories} Calorías</span>
            <span>${meal.protein} Proteína</span>
          </div>
          <a class="text-link" href="meals/${meal.slug}.html">Ver Comída</a>
        </div>
      </article>
    `).join("");
  }

  if (mealPage) {
    const slug = mealPage.getAttribute("data-meal-page");
    const meal = meals.find((item) => item.slug === slug) || meals[0];
    const related = meals.filter((item) => item.slug !== meal.slug).slice(0, 3);
    document.title = `${meal.name} | Good Meals`;
    const mealHeroImage = document.querySelector("#mealHeroImage");
    mealHeroImage.className = mealVisualClass(meal, "meal-detail__image");
    if (meal.image) {
      mealHeroImage.style.backgroundImage = `url('${assetPrefix}${meal.image}')`;
    }
    mealHeroImage.setAttribute("role", "img");
    mealHeroImage.setAttribute("aria-label", `${meal.name} prepared meal`);
    document.querySelector("#mealName").textContent = meal.name;
    document.querySelector("#mealDesc").textContent = meal.description;
    document.querySelector("#mealLabels").innerHTML = meal.labels.map((label) => `<span>${label}</span>`).join("");
    document.querySelector("#mealNutrition").innerHTML = [
      ["Calorías", meal.calories],
      ["Proteína", meal.protein],
      ["Carbohidratos", meal.carbs],
      ["Grasas", meal.fat]
    ].map(([label, value]) => `<div><span>${label}</span><strong>${value}</strong></div>`).join("");
    document.querySelector("#mealIngredients").innerHTML = meal.ingredients.map((item) => `<li>${item}</li>`).join("");
    document.querySelector("#mealAllergens").textContent = meal.allergens.length ? meal.allergens.join(", ") : "No hay alérgenos en la lista de ingredientes.";
    document.querySelector("#mealHeating").textContent = meal.heating;
    document.querySelector("#relatedMeals").innerHTML = related.map((item) => `
      <a class="related-card" href="${item.slug}.html">
        <span class="${mealVisualClass(item, "related-card__image")}"${mealVisualStyle(item)}></span>
        <strong>${item.name}</strong>
        <small>${item.calories} cal / ${item.protein} protein</small>
      </a>
    `).join("");
  }

  document.querySelectorAll("form").forEach((form) => {
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }
      const button = form.querySelector("button");
      if (!button) return;
      const original = button.textContent;
      const status = form.querySelector(".form-status");
      const endpoint = form.getAttribute("data-endpoint");
      const loadingText = form.getAttribute("data-loading") || original;
      const successText = form.getAttribute("data-success") || "Added";
      const statusText = form.getAttribute("data-status") || "";
      button.disabled = true;
      button.textContent = loadingText;

      if (endpoint) {
        if (status) status.textContent = "";

        try {
          const response = await fetch(endpoint, {
            method: "POST",
            headers: {
              Accept: "application/json",
              "Content-Type": "application/json"
            },
            body: JSON.stringify(Object.fromEntries(new FormData(form).entries()))
          });
          const result = await response.json().catch(() => ({}));

          if (!response.ok || !result.ok) {
            const fieldErrors = result.errors ? Object.values(result.errors).join(" ") : "";
            throw new Error(fieldErrors || result.message || "No pudimos enviar el mensaje. Intentalo de nuevo.");
          }

          button.textContent = successText;
          if (status) status.textContent = result.message || statusText;
          form.reset();

          window.setTimeout(() => {
            button.disabled = false;
            button.textContent = original;
            if (status) status.textContent = "";
          }, 3000);
        } catch (error) {
          button.disabled = false;
          button.textContent = original;
          if (status) status.textContent = error.message;
        }

        return;
      }

      button.textContent = successText;
      if (status) status.textContent = statusText;
      window.setTimeout(() => {
        button.disabled = false;
        button.textContent = original;
        if (status) status.textContent = "";
      }, 1600);
    });
  });

  const carouselTrack = document.querySelector(".menu-carousel__track");
  if (carouselTrack) {
    const scrollCarousel = (direction) => {
      const firstSlide = carouselTrack.querySelector(".menu-slide");
      const slideWidth = firstSlide ? firstSlide.getBoundingClientRect().width : carouselTrack.clientWidth;
      carouselTrack.scrollBy({ left: direction * (slideWidth + 16), behavior: prefersReduced ? "auto" : "smooth" });
    };

    document.querySelector("[data-carousel-prev]")?.addEventListener("click", () => scrollCarousel(-1));
    document.querySelector("[data-carousel-next]")?.addEventListener("click", () => scrollCarousel(1));
  }

  const menuLightbox = document.querySelector("#menuLightbox");
  const menuImageButtons = Array.from(document.querySelectorAll("[data-menu-image]"));
  if (menuLightbox && menuImageButtons.length && typeof menuLightbox.showModal === "function") {
    const lightboxImage = menuLightbox.querySelector(".menu-lightbox__image");
    const closeButton = menuLightbox.querySelector("[data-menu-lightbox-close]");
    let activeMenuImage = 0;

    const openMenuImage = (index) => {
      const trigger = menuImageButtons[index];
      if (!trigger) return;
      activeMenuImage = index;
      lightboxImage.src = trigger.dataset.menuImage;
      lightboxImage.alt = trigger.dataset.menuAlt;
      if (!menuLightbox.open) {
        menuLightbox.showModal();
        closeButton.focus({ preventScroll: true });
      }
    };

    menuImageButtons.forEach((button, index) => {
      button.addEventListener("click", () => openMenuImage(index));
    });

    closeButton.addEventListener("click", () => menuLightbox.close());
    menuLightbox.addEventListener("click", (event) => {
      if (event.target === menuLightbox) menuLightbox.close();
    });
    menuLightbox.addEventListener("keydown", (event) => {
      if (event.key === "ArrowRight") {
        event.preventDefault();
        openMenuImage((activeMenuImage + 1) % menuImageButtons.length);
      }
      if (event.key === "ArrowLeft") {
        event.preventDefault();
        openMenuImage((activeMenuImage - 1 + menuImageButtons.length) % menuImageButtons.length);
      }
    });
  }

  const revealItems = document.querySelectorAll("[data-reveal]");
  if (!prefersReduced && "IntersectionObserver" in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.14 });
    revealItems.forEach((item) => observer.observe(item));
  } else {
    revealItems.forEach((item) => item.classList.add("is-visible"));
  }
})();
