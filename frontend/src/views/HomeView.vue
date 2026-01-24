<template>
  <div>
    <!-- Hero Section -->
    <section 
      class="relative bg-gradient-to-br from-primary-600 to-primary-800 text-white py-20 md:py-32"
      style="background: linear-gradient(135deg, rgba(79, 70, 229, 0.95), rgba(99, 102, 241, 0.9)), url('https://ausbildungszentrum.homocanis.de/wp-content/uploads/2020/09/pet-01.jpg'); background-size: cover; background-position: center;"
    >
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center">
          <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold mb-6 drop-shadow-lg">
            Willkommen bei der Hundeschule HomoCanis
          </h1>
          <p class="text-xl md:text-2xl mb-8 max-w-3xl mx-auto text-gray-100">
            Professionelles Hundetraining mit Herz und Verstand – für eine harmonische Mensch-Hund-Beziehung
          </p>
          <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <RouterLink to="/contact" class="btn bg-white text-primary-700 hover:bg-gray-100 px-8 py-3 text-lg font-semibold">
              Kontakt aufnehmen
            </RouterLink>
            <RouterLink to="/login" class="btn bg-primary-800 hover:bg-primary-900 text-white border-2 border-white px-8 py-3 text-lg font-semibold">
              Zum Login
            </RouterLink>
          </div>
        </div>
      </div>
    </section>

    <!-- Features Section -->
    <section class="py-16 bg-white dark:bg-gray-800">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
          <h2 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white mb-4">
            Unsere Leistungen
          </h2>
          <p class="text-lg text-gray-600 dark:text-gray-300 max-w-2xl mx-auto">
            Ich biete ein umfassendes Angebot für die Ausbildung Ihres Hundes und die Stärkung Ihrer Bindung
          </p>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
          <!-- Feature 1 -->
          <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6 hover:shadow-lg transition-shadow">
            <div class="w-12 h-12 bg-primary-100 dark:bg-primary-900 rounded-lg flex items-center justify-center mb-4">
              <span class="text-2xl">🐕</span>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">
              Welpenkurse
            </h3>
            <p class="text-gray-600 dark:text-gray-300">
              Der beste Start für Ihren Welpen – Sozialisierung, Grundkommandos und wichtige Verhaltensregeln
            </p>
          </div>

          <!-- Feature 2 -->
          <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6 hover:shadow-lg transition-shadow">
            <div class="w-12 h-12 bg-primary-100 dark:bg-primary-900 rounded-lg flex items-center justify-center mb-4">
              <span class="text-2xl">📚</span>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">
              Grundausbildung
            </h3>
            <p class="text-gray-600 dark:text-gray-300">
              Aufbau solider Grundkommandos und Etablierung einer vertrauensvollen Kommunikation
            </p>
          </div>

          <!-- Feature 3 -->
          <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6 hover:shadow-lg transition-shadow">
            <div class="w-12 h-12 bg-primary-100 dark:bg-primary-900 rounded-lg flex items-center justify-center mb-4">
              <span class="text-2xl">🎯</span>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">
              Verhaltensberatung
            </h3>
            <p class="text-gray-600 dark:text-gray-300">
              Individuelle Lösungen bei Verhaltensproblemen – professionell und einfühlsam
            </p>
          </div>

          <!-- Feature 4 -->
          <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6 hover:shadow-lg transition-shadow">
            <div class="w-12 h-12 bg-primary-100 dark:bg-primary-900 rounded-lg flex items-center justify-center mb-4">
              <span class="text-2xl">🏃</span>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">
              Einzeltraining
            </h3>
            <p class="text-gray-600 dark:text-gray-300">
              Maßgeschneiderte Trainingseinheiten für spezielle Bedürfnisse und Ziele
            </p>
          </div>

          <!-- Feature 5 -->
          <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6 hover:shadow-lg transition-shadow">
            <div class="w-12 h-12 bg-primary-100 dark:bg-primary-900 rounded-lg flex items-center justify-center mb-4">
              <span class="text-2xl">👥</span>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">
              Gruppenkurse
            </h3>
            <p class="text-gray-600 dark:text-gray-300">
              Training in der Gruppe für bessere Sozialisierung und gemeinsame Lernerfolge
            </p>
          </div>

          <!-- Feature 6 -->
          <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6 hover:shadow-lg transition-shadow">
            <div class="w-12 h-12 bg-primary-100 dark:bg-primary-900 rounded-lg flex items-center justify-center mb-4">
              <span class="text-2xl">📱</span>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">
              Online-Buchung
            </h3>
            <p class="text-gray-600 dark:text-gray-300">
              Einfache und bequeme Terminbuchung über unser modernes Verwaltungssystem
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- About Section -->
    <section class="py-16 bg-gray-50 dark:bg-gray-900">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="lg:grid lg:grid-cols-2 lg:gap-12 items-center">
          <div>
            <h2 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white mb-6">
              Über die Hundeschule
            </h2>
            <p class="text-lg text-gray-600 dark:text-gray-300 mb-4">
              Die Hundeschule HomoCanis steht für professionelles, modernes Hundetraining auf wissenschaftlicher Basis. 
              Wir arbeiten mit positiver Verstärkung und legen großen Wert auf eine respektvolle Kommunikation zwischen Mensch und Hund.
            </p>
            <p class="text-lg text-gray-600 dark:text-gray-300 mb-4">
              Das Team besteht aus einem erfahrenen, zertifizierten Hundetrainer, der sich kontinuierlich weiterbildet 
              und auf dem neuesten Stand der Verhaltensforschung bleibt.
            </p>
            <p class="text-lg text-gray-600 dark:text-gray-300">
              Mit dem modernen Online-Verwaltungssystem können Sie ganz einfach Termine buchen, 
              den Trainingsfortschritt Ihres Hundes verfolgen und direkt mit mir kommunizieren.
            </p>
          </div>
          <div class="mt-8 lg:mt-0">
            <img 
              src="@/assets/HomoCanis.jpg" 
              alt="Hundeschule HomoCanis" 
              class="rounded-lg shadow-xl w-full"
            >
          </div>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section class="py-16 bg-primary-600 dark:bg-primary-700 text-white">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl md:text-4xl font-bold mb-6">
          Bereit für den ersten Schritt?
        </h2>
        <p class="text-xl mb-8 max-w-2xl mx-auto">
          Nehmen Sie Kontakt mit uns auf und lassen Sie uns gemeinsam an einer harmonischen Beziehung zu Ihrem Hund arbeiten
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <RouterLink to="/contact" class="btn bg-white text-primary-700 hover:bg-gray-100 px-8 py-3 text-lg font-semibold">
            Jetzt Kontakt aufnehmen
          </RouterLink>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { onMounted } from 'vue'

// SEO Meta Tags
onMounted(() => {
  // Update page title
  document.title = 'Hundeschule HomoCanis - Professionelles Hundetraining'
  
  // Update or create meta description
  let metaDescription = document.querySelector('meta[name="description"]')
  if (!metaDescription) {
    metaDescription = document.createElement('meta')
    metaDescription.setAttribute('name', 'description')
    document.head.appendChild(metaDescription)
  }
  metaDescription.setAttribute('content', 'Hundeschule HomoCanis bietet professionelles Hundetraining mit Herz und Verstand. Welpenkurse, Grundausbildung, Verhaltensberatung und mehr. Jetzt Termin buchen!')
  
  // Add keywords meta tag
  let metaKeywords = document.querySelector('meta[name="keywords"]')
  if (!metaKeywords) {
    metaKeywords = document.createElement('meta')
    metaKeywords.setAttribute('name', 'keywords')
    document.head.appendChild(metaKeywords)
  }
  metaKeywords.setAttribute('content', 'Hundeschule, Hundetraining, Welpen, Verhaltensberatung, HomoCanis, Hundekurse, Hundeausbildung')

  // Open Graph tags for social media
  const ogTags = [
    { property: 'og:title', content: 'Hundeschule HomoCanis - Professionelles Hundetraining' },
    { property: 'og:description', content: 'Professionelles Hundetraining mit Herz und Verstand – für eine harmonische Mensch-Hund-Beziehung' },
    { property: 'og:type', content: 'website' },
    { property: 'og:locale', content: 'de_DE' }
  ]

  ogTags.forEach(tag => {
    let ogTag = document.querySelector(`meta[property="${tag.property}"]`)
    if (!ogTag) {
      ogTag = document.createElement('meta')
      ogTag.setAttribute('property', tag.property)
      document.head.appendChild(ogTag)
    }
    ogTag.setAttribute('content', tag.content)
  })
})
</script>
