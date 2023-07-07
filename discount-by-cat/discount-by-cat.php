<?php
/*
Plugin Name: Mi Plugin Descuento por Categoría
Description: Plugin sencillo para agregar descuentos por categoría en WooCommerce.
Version: 1.0
Author: Tu Nombre
*/

// Agregar la página de configuración del plugin en el menú de administración
add_action('admin_menu', 'agregar_pagina_configuracion');

function agregar_pagina_configuracion()
{
  add_submenu_page(
    'woocommerce',
    'Descuento por Categoría',
    'Descuento por Categoría',
    'manage_options',
    'descuento-por-categoria',
    'mostrar_pagina_configuracion'
  );
}

function mostrar_pagina_configuracion()
{
  // Comprobar permisos de administrador
  if (!current_user_can('manage_options')) {
    return;
  }

  // Guardar el porcentaje de descuento si se envía el formulario
  if (isset($_POST['guardar_descuento'])) {
    $categoria_id = absint($_POST['categoria_id']);
    $porcentaje_descuento = intval($_POST['porcentaje_descuento']);

    // Validar porcentaje de descuento
    if ($porcentaje_descuento > 0 && $porcentaje_descuento <= 100) {
      update_option('descuento_categoria_' . $categoria_id, $porcentaje_descuento);
      echo '<div class="notice notice-success"><p>Descuento guardado correctamente.</p></div>';
    } else {
      echo '<div class="notice notice-error"><p>El porcentaje de descuento debe ser mayor que 0 y menor o igual a 100.</p></div>';
    }
  }

  // Obtener todas las categorías de productos
  $categorias = get_terms(
    array(
      'taxonomy' => 'product_cat',
      'hide_empty' => false,
    )
  );
  ?>
  <div class="wrap">
    <h1>Descuento por Categoría</h1>
    <form method="post" action="">
      <table class="form-table">
        <tr valign="top">
          <th scope="row">Categoría:</th>
          <td>
            <select name="categoria_id">
              <?php foreach ($categorias as $categoria): ?>
                <option value="<?php echo $categoria->term_id; ?>"><?php echo $categoria->name; ?></option>
              <?php endforeach; ?>
            </select>
          </td>
        </tr>
        <tr valign="top">
          <th scope="row">Porcentaje de descuento:</th>
          <td><input type="number" name="porcentaje_descuento" min="1" max="100" step="1" required></td>
        </tr>
      </table>
      <p class="submit"><input type="submit" name="guardar_descuento" class="button-primary" value="Guardar Descuento">
      </p>
    </form>

    <h2>Categorías con descuento</h2>
    <table class="wp-list-table widefat fixed striped">
      <thead>
        <tr>
          <th>Categoría</th>
          <th>Descuento</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($categorias as $categoria): ?>
          <?php $porcentaje_descuento = get_option('descuento_categoria_' . $categoria->term_id); ?>
          <?php if ($porcentaje_descuento): ?>
            <tr>
              <td>
                <?php echo $categoria->name; ?>
              </td>
              <td>
                <?php echo $porcentaje_descuento; ?>%
              </td>
            </tr>
          <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php
}

// Aplicar descuento por categoría en el carrito
function aplicar_descuento_por_categoria($cart_object)
{
  if (is_admin() && !defined('DOING_AJAX')) {
    return;
  }

  foreach ($cart_object->get_cart_contents() as $key => $value) {
    $producto_categorias = get_the_terms($value['product_id'], 'product_cat');

    if (!empty($producto_categorias) && !is_wp_error($producto_categorias)) {
      foreach ($producto_categorias as $categoria) {
        $porcentaje_descuento = get_option('descuento_categoria_' . $categoria->term_id);

        if ($porcentaje_descuento) {
          $precio = floatval($value['data']->get_price());
          $descuento = ($precio * $porcentaje_descuento) / 100;
          $nuevo_precio = $precio - $descuento;

          $value['data']->set_price($nuevo_precio);
          $value['data']->set_regular_price($precio); // Establecer precio original sin descuento para mostrarlo en la página del producto
        }
      }
    }
  }
}
add_action('woocommerce_before_calculate_totals', 'aplicar_descuento_por_categoria', 10, 1);

// Mostrar el descuento en la página del producto y otros lugares
function mostrar_descuento_producto($price, $product)
{
  $porcentaje_descuento = get_option('descuento_categoria_' . $product->get_category_ids()[0]);

  if ($porcentaje_descuento) {
    $precio = floatval($product->get_regular_price());
    $descuento = ($precio * $porcentaje_descuento) / 100;
    $nuevo_precio = $precio - $descuento;

    if ($nuevo_precio < $precio) {
      $price = '<del>' . wc_price($precio) . '</del> <ins>' . wc_price($nuevo_precio) . '</ins>';
    }
  }

  return $price;
}
add_filter('woocommerce_get_price_html', 'mostrar_descuento_producto', 10, 2);

// Mostrar porcentaje de descuento en los productos
function mostrar_porcentaje_descuento($price, $product)
{
  $porcentaje_descuento = get_option('descuento_categoria_' . $product->get_category_ids()[0]);

  if ($porcentaje_descuento) {
    $price .= ' <span class="descuento text-lg font-bold block">' . $porcentaje_descuento . '% Desc.</span>';
  }

  return $price;
}
add_filter('woocommerce_get_price_html', 'mostrar_porcentaje_descuento', 10, 2);